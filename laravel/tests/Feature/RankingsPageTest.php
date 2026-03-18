<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RankingsPageTest extends TestCase
{
    public function test_rankings_page_renders_creator_rows(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$source] = $repository->syncSources([
            ['handle' => 'demo', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $source['id'], [
            'tweetId' => '4001',
            'tweet' => [
                'authorHandle' => 'demo',
                'authorName' => 'Demo',
                'text' => '榜单样本',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);

        $response = $this->get('/rankings');

        $response->assertOk();
        $response->assertSee('榜单', false);
        $response->assertSee('#1', false);
        $response->assertSee('&#64;demo', false);
        $response->assertSee('7天更新', false);
        $response->assertSee('data-auth-modal-trigger="true"', false);
        $response->assertDontSee('v1 先只看创作者更新活跃度。排序口径固定为近 7 天更新数、最近更新时间、总视频数，避免伪热度噪音。', false);
    }

    public function test_rankings_page_falls_back_to_other_creators_when_only_one_is_active_recently(): void
    {
        $repository = $this->useShortVideoDatabase();
        [$activeSource, $olderSource] = $repository->syncSources([
            ['handle' => 'active_creator', 'enabled' => true],
            ['handle' => 'older_creator', 'enabled' => true],
        ]);

        $this->insertResolvedTweet($repository, $activeSource['id'], [
            'tweetId' => '4002',
            'tweet' => [
                'authorHandle' => 'active_creator',
                'authorName' => 'Active Creator',
                'text' => '最近活跃',
                'postedAt' => now()->subDay()->toISOString(),
            ],
        ]);

        $this->insertResolvedTweet($repository, $olderSource['id'], [
            'tweetId' => '4003',
            'tweet' => [
                'authorHandle' => 'older_creator',
                'authorName' => 'Older Creator',
                'text' => '历史内容',
                'postedAt' => now()->subDays(20)->toISOString(),
            ],
        ]);

        $response = $this->get('/rankings');

        $response->assertOk();
        $response->assertSee('&#64;active_creator', false);
        $response->assertSee('&#64;older_creator', false);
        $response->assertSee('#2', false);
    }
}
