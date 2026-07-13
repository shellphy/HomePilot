<?php

namespace App\Services;

use App\Enums\MatterReviewStatus;
use App\Enums\PartyReviewStatus;
use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Party;
use App\Models\Resident;
use App\Models\Stance;

/**
 * 「待我处理」：把散落各处的下一步聚成一个有序列表，全部从当前状态派生——
 * 做完（答了/确认了/评价了/看了）即自动消失，不落待办表。
 * 排序：有截止时间的在前（越近越前）> 需要拍板的动作 > 纯知会。
 */
class ResidentTodoList
{
    private const WEIGHT_ACTION = 1;

    private const WEIGHT_INFO = 2;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function for(Resident $resident): array
    {
        $todos = [
            ...$this->censusesToAnswer($resident),
            ...$this->groupbuysToConfirm($resident),
            ...$this->mattersToReview($resident),
            ...$this->questionsToAnswer($resident),
            ...$this->dealsToPost($resident),
            ...$this->mattersWithProgress($resident),
            ...$this->adminQueues($resident),
        ];

        usort($todos, fn (array $a, array $b): int => [$a['deadline'] === null, $a['deadline'] ?? '', $a['weight']]
            <=> [$b['deadline'] === null, $b['deadline'] ?? '', $b['weight']]);

        // 一件事只留优先级最高的一条：有行动待办（确认/评价/回答/公示）就不再作为「查看进展」重复出现
        $seen = [];
        $todos = array_filter($todos, function (array $todo) use (&$seen): bool {
            if ($todo['matter_id'] === null) {
                return true;
            }
            if (isset($seen[$todo['matter_id']])) {
                return false;
            }
            $seen[$todo['matter_id']] = true;

            return true;
        });

        return array_values(array_map(fn (array $todo): array => [
            'type' => $todo['type'],
            'title' => $todo['title'],
            'action' => $todo['action'],
            'matter_id' => $todo['matter_id'],
            'deadline' => $todo['deadline'],
        ], $todos));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function censusesToAnswer(Resident $resident): array
    {
        return Matter::approved()
            ->where('type', 'census')
            ->where('state', 'open')
            ->whereDoesntHave('stances', fn ($query) => $query
                ->where('mode', Stance::MODE_REGISTER)
                ->where('resident_id', $resident->id))
            ->get()
            ->map(fn (Matter $matter): array => $this->item(
                'census_answer', $matter->title, '去回答', self::WEIGHT_ACTION, $matter,
            ))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupbuysToConfirm(Resident $resident): array
    {
        return Matter::approved()
            ->where('type', 'groupbuy')
            ->where('state', 'open')
            ->whereHas('joins', fn ($query) => $query
                ->where('resident_id', $resident->id)
                ->where('payload->stage', Stance::JOIN_STAGE_INTENT))
            ->get()
            ->map(fn (Matter $matter): array => $this->item(
                'groupbuy_confirm', $matter->title, '确认参团', self::WEIGHT_ACTION, $matter,
            ))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mattersToReview(Resident $resident): array
    {
        return Matter::approved()
            ->whereHas('joins', fn ($query) => $query->where('resident_id', $resident->id))
            ->get()
            ->filter(function (Matter $matter) use ($resident): bool {
                $type = MatterTypeRegistry::for($matter->type);

                return $type->reviewOpen($matter)
                    && $type->isParticipant($matter, $resident)
                    && ! $matter->stances()
                        ->where('mode', Stance::MODE_REVIEW)
                        ->where('resident_id', $resident->id)
                        ->exists();
            })
            ->map(fn (Matter $matter): array => $this->item(
                'review', $matter->title, '去评价', self::WEIGHT_ACTION, $matter,
            ))->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionsToAnswer(Resident $resident): array
    {
        return Matter::approved()
            ->where('initiator_id', $resident->id)
            ->whereHas('questions', fn ($query) => $query->whereNull('answer'))
            ->withCount(['questions as unanswered_count' => fn ($query) => $query->whereNull('answer')])
            ->get()
            ->map(function (Matter $matter): array {
                $count = (int) $matter->getAttribute('unanswered_count');

                return $this->item('answer_question', $matter->title, "回答 {$count} 个提问", self::WEIGHT_ACTION, $matter);
            })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dealsToPost(Resident $resident): array
    {
        return Matter::approved()
            ->where('initiator_id', $resident->id)
            ->where('type', 'groupbuy')
            ->where('state', 'done')
            ->get()
            ->filter(fn (Matter $matter): bool => empty($matter->payloadValue('final_terms')))
            ->map(fn (Matter $matter): array => $this->item(
                'post_deal', $matter->title, '发布成交公示', self::WEIGHT_ACTION, $matter,
            ))->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mattersWithProgress(Resident $resident): array
    {
        return $resident->unseenActivityMatters()
            ->map(fn (Matter $matter): array => $this->item(
                'progress', $matter->title, '查看进展', self::WEIGHT_INFO, $matter,
            ))->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminQueues(Resident $resident): array
    {
        if (! $resident->is_admin) {
            return [];
        }

        $todos = [];

        $pendingMatters = Matter::where('review_status', MatterReviewStatus::Pending->value)->count();
        if ($pendingMatters > 0) {
            $todos[] = $this->item('admin_review', '事项审核', "{$pendingMatters} 件待审核", self::WEIGHT_ACTION);
        }

        $pendingParties = Party::where('review_status', PartyReviewStatus::Pending->value)->count();
        if ($pendingParties > 0) {
            $todos[] = $this->item('admin_party', '相关方核验', "{$pendingParties} 家待核验", self::WEIGHT_ACTION);
        }

        return $todos;
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $type, string $title, string $action, int $weight, ?Matter $matter = null): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'action' => $action,
            'weight' => $weight,
            'matter_id' => $matter?->id,
            'deadline' => $matter?->registration_deadline_at?->toDateString(),
        ];
    }
}
