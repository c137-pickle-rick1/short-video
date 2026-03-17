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
        $response->assertSee('创作者', false);
        $response->assertSee('&#64;demo', false);
        $response->assertSee('7天更新', false);
        $response->assertSee('/socialGraph.js', false);
    }
}
