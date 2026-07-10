<?php

namespace App\Matters;

use App\Models\Matter;

/**
 * 公告：最简单的事务类型（单向公示），
 * 存在的意义是证明"新场景 = 一个类型类"这条内核承诺成立。
 */
class NoticeType extends MatterType
{
    public function key(): string
    {
        return 'notice';
    }

    public function label(): string
    {
        return '公告';
    }

    public function states(): array
    {
        return [
            'published' => '已发布',
            'archived' => '已归档',
        ];
    }

    public function payloadRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /** 公告只能由管理端发布。 */
    public function userInitiatable(): bool
    {
        return false;
    }

    /** 归档的公告退出事务流。 */
    public function visibleInList(Matter $matter): bool
    {
        return $matter->state === 'published';
    }

    public function payloadFrom(array $validated): array
    {
        return ['body' => $validated['body'] ?? ''];
    }

    /** 公告置顶（归档的沉底交给列表按状态过滤）。 */
    public function sortWeight(Matter $matter): int
    {
        return $matter->state === 'published' ? -1 : 9;
    }
}
