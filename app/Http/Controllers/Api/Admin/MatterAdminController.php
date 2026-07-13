<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\MatterReviewStatus;
use App\Events\MatterApproved;
use App\Events\MatterRejected;
use App\Http\Controllers\Api\Concerns\ResolvesResident;
use App\Http\Controllers\Controller;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Stance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理端 · 事项：审核队列与登记明细。
 * 创建/详情/编辑/删除已并入用户端 MatterController（按 is_admin 做字段级授权）。
 */
class MatterAdminController extends Controller
{
    use ResolvesResident;

    /**
     * 全部事项（含待审核）；?pending=1 只看待审核队列。
     */
    public function index(Request $request): JsonResponse
    {
        $matters = Matter::query()
            ->when($request->boolean('pending'), fn ($query) => $query->where('review_status', MatterReviewStatus::Pending->value))
            ->with(['initiator', 'initiatorParty'])
            ->withCount('joins')
            ->withCount(['stances as register_count' => fn ($query) => $query->where('mode', Stance::MODE_REGISTER)])
            ->orderByRaw('CASE WHEN review_status = ? THEN 0 WHEN review_status = ? THEN 1 ELSE 2 END', [
                MatterReviewStatus::Pending->value,
                MatterReviewStatus::Rejected->value,
            ])
            ->orderByRaw('CASE WHEN review_status = ? THEN created_at END ASC', [MatterReviewStatus::Pending->value])
            ->latest('created_at')
            ->get();

        return response()->json([
            // 只数真正待处理的（驳回态在等发起人修改，不占管理员的队列徽标）
            'data' => $matters->map(fn (Matter $matter): array => $this->present($matter)),
            'pending_count' => Matter::where('review_status', MatterReviewStatus::Pending->value)->count(),
        ]);
    }

    /**
     * 审核：通过（公示给全小区）或驳回/撤下（附理由，发起人在详情页看到，编辑后即重新提交）。
     */
    public function approve(Request $request, Matter $matter): JsonResponse
    {
        $validated = $request->validate([
            'is_approved' => ['required', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $wasApproved = $matter->is_approved;

        if ($validated['is_approved']) {
            $matter->approve();
        } else {
            $matter->reject($validated['reason'] ?? '');
        }

        // 审核结果对发起人是关键动态：喂给「我的」页未读红点。
        // 通过只在状态真的翻正时记；驳回每次都记（理由可能更新）。
        if ($matter->is_approved && ! $wasApproved) {
            $matter->recordActivity($this->resident($request));
            MatterApproved::dispatch($matter);
        }

        if (! $matter->is_approved) {
            $matter->recordActivity($this->resident($request));
            MatterRejected::dispatch($matter);
        }

        return response()->json(['data' => $this->present($matter)]);
    }

    /**
     * 登记明细（含楼栋/房号/手机等仅管理员可见字段），答案换算成题面文字。
     */
    public function registrations(Matter $matter): JsonResponse
    {
        $questions = collect($matter->payloadList('modules'))
            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
            ->keyBy('key');

        $registrations = $matter->stances()
            ->where('mode', Stance::MODE_REGISTER)
            ->with('resident')
            ->latest()
            ->get()
            ->map(function (Stance $stance) use ($questions): array {
                $answers = $stance->payload['answers'] ?? [];

                return [
                    'id' => $stance->id,
                    'unit_label' => $stance->resident->unit_label,
                    'room_label' => $stance->resident->room_label,
                    'layout_label' => $stance->resident->layout_label,
                    'nickname' => $stance->resident->nickname,
                    'phone' => $stance->resident->phone,
                    'created_at' => $stance->created_at?->format('Y-m-d H:i'),
                    'answers' => collect(is_array($answers) ? $answers : [])
                        ->map(fn (mixed $value, int|string $key): array => [
                            'question' => $questions[$key]['text'] ?? $key,
                            'answer' => is_array($value) ? implode('、', $value) : (string) $value,
                        ])
                        ->values(),
                ];
            });

        return response()->json(['data' => $registrations]);
    }

    /**
     * 审核队列里的发起人署名：相关方身份的事项亮明身份快照（审核商家发起的事项时一眼可辨）。
     */
    private function initiatorLabel(Matter $matter): string
    {
        if ($matter->initiatorParty) {
            return $matter->initiatorParty->typeLabel().' · '.$matter->initiatorParty->name
                .($matter->initiatorParty->is_listed ? '（已认证）' : '（未认证）');
        }

        return $matter->initiator?->displayName() ?: '管理员发布';
    }

    /**
     * 管理端一览用的完整视图：原始 payload（编辑表单要用）+ 审核状态 + 发起人。
     *
     * @return array<string, mixed>
     */
    private function present(Matter $matter): array
    {
        $type = MatterTypeRegistry::for($matter->type);

        return [
            'id' => $matter->id,
            'type' => $matter->type,
            'type_label' => $type->label(),
            'title' => $matter->title,
            'category' => $matter->category,
            'state' => $matter->state,
            'state_label' => $type->stateLabel($matter->state),
            // 管理端可选全部状态（含旁路终态），作为纠错通道
            'states' => $type->allStates(),
            'is_approved' => $matter->is_approved,
            'review_status' => $matter->review_status->value,
            'review_status_label' => $matter->review_status->label(),
            'reject_reason' => $matter->reject_reason,
            'target_count' => $matter->target_count,
            'initiator' => $this->initiatorLabel($matter),
            'initiator_party_id' => $matter->initiator_party_id,
            'join_count' => (int) ($matter->joins_count ?? 0),
            'register_count' => (int) ($matter->register_count ?? 0),
            'payload' => $matter->payload ?? (object) [],
            'created_at' => $matter->created_at?->format('Y-m-d H:i'),
            'pending_hours' => $matter->review_status === MatterReviewStatus::Pending
                ? (int) $matter->created_at->diffInHours(now())
                : 0,
            'needs_attention' => $matter->review_status === MatterReviewStatus::Pending
                && $matter->created_at->lte(now()->subDay()),
        ];
    }
}
