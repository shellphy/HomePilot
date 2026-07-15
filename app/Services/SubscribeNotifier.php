<?php

namespace App\Services;

use App\Matters\MatterType;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\MatterUpdate;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;
use Illuminate\Database\Eloquent\Collection;

/**
 * 订阅消息通知：站内各类动态统一翻译成「活动状态提醒」模板的四个字段再下发。
 * 单模板打全场景——short_thing3（≤5 字）当事件词用（已成团/有新进展/新增报名/收到评价…），
 * thing4（≤20 字）装动态内容。发送失败（额度不足/拒收）静默跳过，站内红点兜底。
 */
class SubscribeNotifier
{
    /** 模板字段长度上限：thing 20 字，short_thing 5 字，超长截断。 */
    private const THING_LIMIT = 20;

    private const SHORT_THING_LIMIT = 5;

    public function __construct(private WeChat $weChat) {}

    public function matterApproved(Matter $matter): void
    {
        $this->notifyMatter($matter, [$matter->initiator], '已公示', '审核已通过，全小区可见');
    }

    public function matterRejected(Matter $matter): void
    {
        $reason = $matter->reject_reason;

        $this->notifyMatter($matter, [$matter->initiator], '未过审', $reason !== '' ? $reason : '可修改内容后重新提交');
    }

    /**
     * 状态流转（成团/谈崩/收场/有结果…）→ 通知参与者与发起人，排除流转操作人自己。
     */
    public function stateChanged(Matter $matter, ?Resident $actor = null): void
    {
        $this->notifyMatter(
            $matter,
            [...$this->participants($matter), $matter->initiator],
            MatterTypeRegistry::for($matter->type)->stateLabel($matter->state),
            $this->stateHint($matter),
            except: $actor,
        );
    }

    /**
     * 时间线进展/官方回应 → 通知参与者与发起人，排除发布人自己。
     */
    public function updatePosted(MatterUpdate $update, ?Resident $author = null): void
    {
        $matter = $update->matter;

        $this->notifyMatter(
            $matter,
            [...$this->participants($matter), $matter->initiator],
            $update->author_party_id !== null ? '官方回应' : '有新进展',
            $update->content,
            except: $author,
        );
    }

    /**
     * 团购条款实质变更 → 通知被降级的参团者审核通过后重新确认。
     *
     * @param  array<int, int>  $residentIds
     */
    public function termsRevised(Matter $matter, array $residentIds): void
    {
        $recipients = Resident::whereIn('id', $residentIds)->get()->all();

        $this->notifyMatter($matter, $recipients, '条款有变', '团购条款有更新，审核通过后请重新确认参团');
    }

    public function dealPosted(Matter $matter): void
    {
        $recipients = $matter->confirmedJoins()->with('resident')->get()
            ->map(fn (Stance $join): Resident => $join->resident)
            ->all();

        $this->notifyMatter($matter, $recipients, '成交公示', '成交条件与让利去向已公示', except: $matter->initiator);
    }

    /**
     * 新加入/意向升级 → 通知发起人（自己加入自己的事项不通知）。
     */
    public function joined(Matter $matter, Stance $join, bool $upgraded = false): void
    {
        $joiner = $join->resident;

        [$word, $verb] = match (true) {
            $upgraded => ['确认参团', ' 确认参团'],
            $matter->type === 'aid' => ['有人响应', ' 响应了你'],
            default => ['新增报名', ' 加入了名单'],
        };

        $this->notifyMatter($matter, [$matter->initiator], $word, $joiner->displayName().$verb, except: $joiner);
    }

    public function reviewed(Matter $matter, Stance $review): void
    {
        $rating = (int) ($review->payload['rating'] ?? 0);

        $this->notifyMatter($matter, [$matter->initiator], '收到评价', "收到一条 {$rating} 星评价", except: $review->resident);
    }

    /**
     * 相关方核验通过 → 通知档案归属人（当前绑定的成员优先，其次最近绑定过的）。
     */
    public function partyListed(Party $party): void
    {
        $owner = $this->partyOwner($party);

        if ($owner === null) {
            return;
        }

        $this->send(
            $owner,
            'pages/party/index?id='.$party->id,
            $party->name,
            $party->typeLabel().'入驻',
            '核验通过',
            '已进入小区公示名录',
        );
    }

    /**
     * 相关方自助入驻 → 通知管理员去核验。
     */
    public function partyRegistered(Party $party): void
    {
        Resident::where('is_admin', true)->get()->each(fn (Resident $admin) => $this->send(
            $admin,
            'pages/admin/parties/index',
            $party->name,
            $party->typeLabel().'入驻',
            '待核验',
            '有新入驻等待核验',
        ));
    }

    /**
     * 相关方核验被驳回 → 通知归属人（附理由，可改后重交）。
     */
    public function partyRejected(Party $party): void
    {
        $owner = $this->partyOwner($party);

        if ($owner === null) {
            return;
        }

        $this->send(
            $owner,
            'pages/party/index?id='.$party->id,
            $party->name,
            $party->typeLabel().'入驻',
            '未通过',
            $party->reject_reason !== '' ? $party->reject_reason : '可修改资料后重新提交',
        );
    }

    /**
     * 档案归属人：当前绑定的成员优先，其次最近绑定过的。
     */
    private function partyOwner(Party $party): ?Resident
    {
        return Resident::where('affiliated_party_id', $party->id)->orderByDesc('updated_at')->first()
            ?? Resident::where('last_party_id', $party->id)->orderByDesc('updated_at')->first();
    }

    /**
     * 事项维度的统一出口：标题/类型来自事项，收件人去空、去重、排除动作发起者后逐个下发。
     *
     * @param  array<int, Resident|null>  $recipients
     */
    private function notifyMatter(Matter $matter, array $recipients, string $stateWord, string $hint, ?Resident $except = null): void
    {
        collect($recipients)
            ->filter()
            ->unique('id')
            ->reject(fn (Resident $recipient): bool => $except !== null && $recipient->id === $except->id)
            ->each(fn (Resident $recipient) => $this->send(
                $recipient,
                'pages/matter/index?id='.$matter->id,
                $matter->title,
                MatterTypeRegistry::for($matter->type)->label(),
                $stateWord,
                $hint,
            ));
    }

    private function send(Resident $recipient, string $page, string $title, string $typeLabel, string $stateWord, string $hint): void
    {
        $this->weChat->sendSubscribeMessage($recipient->openid_mp, $page, [
            'thing1' => ['value' => $this->clip($title, self::THING_LIMIT)],
            'thing5' => ['value' => $this->clip($typeLabel, self::THING_LIMIT)],
            'short_thing3' => ['value' => $this->clip($stateWord, self::SHORT_THING_LIMIT)],
            'thing4' => ['value' => $this->clip($hint, self::THING_LIMIT)],
        ]);
    }

    /**
     * 事项的参与者（接龙 + 征集登记的成员）。
     *
     * @return Collection<int, Resident>
     */
    private function participants(Matter $matter): Collection
    {
        return Resident::whereHas('stances', fn ($query) => $query
            ->where('matter_id', $matter->id)
            ->whereIn('mode', [Stance::MODE_JOIN, Stance::MODE_REGISTER]))
            ->get();
    }

    /**
     * 状态流转的温馨提示：终态点明后续（评价/结果），其余通用。
     */
    private function stateHint(Matter $matter): string
    {
        return match ($matter->state) {
            'done', 'resolved' => '尘埃落定，点击查看结果',
            'closed' => '已结束，感谢参与',
            MatterType::ABORT_STATE => '这件事按「'.MatterTypeRegistry::for($matter->type)->stateLabel($matter->state).'」收场了',
            default => '有新进展，点击查看详情',
        };
    }

    /**
     * 按模板字段上限截断（微信超长直接拒发，宁可省略号也要送达）。
     */
    private function clip(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1).'…';
    }
}
