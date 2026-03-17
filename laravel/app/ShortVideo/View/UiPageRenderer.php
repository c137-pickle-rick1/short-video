<?php

namespace App\ShortVideo\View;

final class UiPageRenderer
{
    public function __construct(
        private readonly FoundationUiComponents $foundationComponents,
        private readonly FeedUiComponents $feedComponents,
        private readonly HomePageRenderer $homePageRenderer,
        private readonly HomePageShellComponents $shellComponents,
        private readonly LoginPageRenderer $loginPageRenderer
    ) {}

    public function renderDocumentHead(string $pageTitle): string
    {
        return <<<HTML
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$this->escape($pageTitle)}</title>
    <link rel="stylesheet" href="/vendor/fonts/fonts.css" />
    <link rel="stylesheet" href="/vendor/phosphor/regular/style.css" />
    <link rel="stylesheet" href="/vendor/phosphor/fill/style.css" />
    <link rel="stylesheet" href="/styles.css" />
HTML;
    }

    public function renderPageHeader(string $loginUrl): string
    {
        return $this->shellComponents->renderPageHeader($loginUrl);
    }

    public function renderShowcase(): string
    {
        $shellSection = $this->renderSection(
            id: 'shell',
            eyebrow: 'Shell',
            title: '页面框架组件',
            content: $this->renderStoryGrid([
                $this->renderStory(
                    title: 'Page Header',
                    preview: $this->shellComponents->renderPageHeaderPreview(route('login'))
                ),
                $this->renderStory(
                    title: 'Desktop Navigation',
                    preview: $this->shellComponents->renderDesktopNavigationPreview($this->navigationItems()),
                    previewClass: $this->demoSurfaceClass('p-4')
                ),
                $this->renderStory(
                    title: 'Mobile Navigation',
                    preview: $this->shellComponents->renderMobileNavigationPreview($this->navigationItems()),
                    previewClass: $this->demoSurfaceClass('p-4')
                ),
            ])
        );

        $atomsSection = $this->renderSection(
            id: 'atoms',
            eyebrow: 'Atoms',
            title: '基础展示组件',
            content: $this->renderStoryGrid([
                $this->renderStory(
                    title: 'Buttons',
                    preview: '<div class="grid gap-4">'.
                        '<div class="flex flex-wrap items-center gap-3">'.
                            $this->foundationComponents->renderButton('发布内容').
                            $this->foundationComponents->renderButton('保存草稿', 'secondary').
                            $this->foundationComponents->renderButton('稍后处理', 'ghost').
                        '</div>'.
                        '<div class="flex flex-wrap items-center gap-3">'.
                            $this->foundationComponents->renderIconButton('ph ph-heart', '点赞').
                            $this->foundationComponents->renderIconButton('ph ph-bookmark-simple', '收藏', active: true).
                            $this->foundationComponents->renderIconButton('ph ph-share-network', '分享', 'solid').
                        '</div>'.
                    '</div>',
                    previewClass: $this->demoSurfaceClass('p-5')
                ),
                $this->renderStory(
                    title: 'Input Field',
                    preview: $this->foundationComponents->renderInputField(
                        label: '视频标题',
                        type: 'text',
                        placeholder: '输入视频标题',
                        autocomplete: 'off',
                        value: 'Lagos city walk',
                        hint: '建议控制在 30 个字符以内。'
                    ),
                    previewClass: 'max-w-md'
                ),
                $this->renderStory(
                    title: 'Menu',
                    preview: $this->foundationComponents->renderMenu('内容操作', [
                        [
                            'icon' => 'ph ph-pencil-simple',
                            'label' => '编辑内容',
                            'description' => '更新标题、封面和文案。',
                        ],
                        [
                            'icon' => 'ph ph-share-network',
                            'label' => '分享链接',
                            'description' => '复制公开访问链接。',
                        ],
                        [
                            'icon' => 'ph ph-trash',
                            'label' => '移至回收站',
                            'description' => '从当前列表中移除这条内容。',
                            'danger' => true,
                        ],
                    ]),
                    previewClass: 'max-w-sm'
                ),
                $this->renderStory(
                    title: 'Author Identity / With Avatar',
                    preview: $this->feedComponents->renderAuthorIdentity(
                        imageUrl: 'https://example.com/avatar.jpg',
                        authorName: 'Lagos Studio',
                        authorHandle: 'lagosstudio',
                        authorInitial: 'L',
                        avatarSizeClass: 'h-12 w-12',
                        nameClass: 'truncate text-base font-semibold text-gray-950',
                        handleClass: 'mt-1 truncate text-sm text-gray-500'
                    ),
                    previewClass: $this->demoSurfaceClass('p-5')
                ),
                $this->renderStory(
                    title: 'Author Identity / Fallback',
                    preview: $this->feedComponents->renderAuthorIdentity(
                        imageUrl: null,
                        authorName: 'Morning Cut',
                        authorHandle: 'morningcut',
                        authorInitial: 'M',
                        avatarSizeClass: 'h-10 w-10',
                        nameClass: 'truncate text-sm font-semibold text-gray-900',
                        handleClass: 'mt-1 truncate text-xs text-gray-500'
                    ),
                    previewClass: $this->demoSurfaceClass('p-5')
                ),
                $this->renderStory(
                    title: 'Duration Badge',
                    preview: '<div class="flex min-h-[180px] items-start rounded-2xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-700 p-5">'.$this->feedComponents->renderDurationBadge('1:24').'</div>',
                    previewClass: $this->demoSurfaceClass('p-4')
                ),
                $this->renderStory(
                    title: 'Empty State Card',
                    preview: $this->feedComponents->renderEmptyStateCard(
                        title: '暂无内容',
                        body: '这个区块演示空状态卡片组件。后续可以继续接收藏页、通知页和占位场景。'
                    ),
                    spanClass: 'lg:col-span-2'
                ),
            ])
        );

        $feedSection = $this->renderSection(
            id: 'feed',
            eyebrow: 'Feed',
            title: '卡片类组件',
            content: $this->renderStoryGrid([
                $this->renderStory(
                    title: 'Feed Card / Resolved',
                    preview: $this->homePageRenderer->renderFeedItem($this->sampleTweet()),
                    previewClass: 'max-w-[420px]'
                ),
                $this->renderStory(
                    title: 'Feed Card / Pending',
                    preview: $this->homePageRenderer->renderFeedItem($this->samplePendingTweet()),
                    previewClass: 'max-w-[420px]'
                ),
                $this->renderStory(
                    title: 'Media Frame / Tall',
                    preview: $this->feedComponents->renderFeedMedia(
                        $this->sampleTweet(),
                        'aspect-[4/5]',
                        '0:42',
                        'lagosstudio'
                    ),
                    previewClass: 'max-w-[340px]'
                ),
                $this->renderStory(
                    title: 'Media Frame / Wide',
                    preview: $this->feedComponents->renderFeedMedia(
                        $this->sampleWideTweet(),
                        'aspect-[6/5]',
                        '2:08',
                        'lagosstudio'
                    ),
                    previewClass: 'max-w-[420px]'
                ),
            ])
        );

        $authSection = $this->renderSection(
            id: 'auth',
            eyebrow: 'Auth',
            title: '登录页组件',
            content: $this->renderStoryGrid([
                $this->renderStory(
                    title: 'Login Card / Default',
                    preview: '<div class="max-w-md">'.$this->loginPageRenderer->renderLoginCard().'</div>',
                    previewClass: 'max-w-md'
                ),
                $this->renderStory(
                    title: 'Login Card / Loading',
                    preview: '<div class="max-w-md">'.$this->loginPageRenderer->renderLoginCard(
                        buttonLabel: '登录中',
                        loading: true,
                        note: '按钮进入加载态时会附带 spinner，并自动变为不可点击。'
                    ).'</div>',
                    previewClass: 'max-w-md'
                ),
                $this->renderStory(
                    title: 'Login Card / Disabled',
                    preview: '<div class="max-w-md">'.$this->loginPageRenderer->renderLoginCard(
                        buttonLabel: '暂不可用',
                        disabled: true,
                        note: '这个状态可用于权限不足、条件未满足或表单校验未完成。'
                    ).'</div>',
                    previewClass: 'max-w-md'
                ),
            ])
        );

        return <<<HTML
          <div class="mx-auto w-full max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8 xl:px-10">
            <article class="mx-auto min-w-0 max-w-[980px]">
              {$this->renderPageIntro()}
              <div class="mt-12 grid gap-16">
                {$shellSection}
                {$atomsSection}
                {$feedSection}
                {$authSection}
              </div>
            </article>
          </div>
HTML;
    }

    private function renderPageIntro(): string
    {
        return <<<HTML
          <header class="border-b border-gray-200 pb-8">
            <p class="text-sm font-medium text-sky-700">Components</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-gray-950 sm:text-[2.75rem]">UI Library</h1>
          </header>
HTML;
    }

    private function renderSection(string $id, string $eyebrow, string $title, string $content): string
    {
        $safeId = $this->escape($id);

        return <<<HTML
          <section id="{$safeId}" class="grid gap-8 border-t border-gray-200 pt-10 first:border-t-0 first:pt-0 scroll-mt-28">
            <div>
              <p class="text-sm font-medium text-sky-700">{$this->escape($eyebrow)}</p>
              <h2 class="mt-3 text-3xl font-semibold tracking-tight text-gray-950">{$this->escape($title)}</h2>
            </div>
            {$content}
          </section>
HTML;
    }

    /**
     * @param  list<string>  $cards
     */
    private function renderStoryGrid(array $cards): string
    {
        return '<div class="grid gap-y-16">'.implode('', $cards).'</div>';
    }

    private function renderStory(
        string $title,
        string $preview,
        string $note = '',
        string $spanClass = '',
        string $previewClass = ''
    ): string {
        $safeTitle = $this->escape($title);
        $noteMarkup = $note !== ''
            ? '<p class="text-sm leading-6 text-gray-500">'.$this->escape($note).'</p>'
            : '';
        $wrapperClass = $previewClass !== '' ? ' class="'.$this->escape($previewClass).'"' : '';
        $storyClass = trim('grid content-start gap-4 '.$spanClass);

        return <<<HTML
          <article class="{$storyClass}">
            <div>
              <h3 class="text-base font-semibold tracking-tight text-gray-950">{$safeTitle}</h3>
            </div>
            <div{$wrapperClass}>
              {$preview}
            </div>
            {$noteMarkup}
          </article>
HTML;
    }

    private function demoSurfaceClass(string $extraClasses = ''): string
    {
        return trim('rounded-2xl border border-gray-200/80 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] '.$extraClasses);
    }

    /**
     * @return array<int, array{icon:string,label:string,active:bool,href:string,avatarUrl?:string|null,avatarInitial?:string|null}>
     */
    private function navigationItems(): array
    {
        return [
            ['icon' => 'ph-fill ph-sparkle', 'label' => '精选', 'active' => true, 'href' => route('home')],
            ['icon' => 'ph ph-compass', 'label' => '探索', 'active' => false, 'href' => route('explore')],
            ['icon' => 'ph ph-chart-bar', 'label' => '榜单', 'active' => false, 'href' => route('rankings')],
            ['icon' => 'ph ph-bookmarks', 'label' => '订阅', 'active' => false, 'href' => route('subscriptions')],
            ['icon' => 'ph ph-sign-in', 'label' => '登录', 'active' => false, 'href' => route('login')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleTweet(): array
    {
        return [
            'tweetId' => 'ui-preview-1001',
            'status' => 'resolved',
            'text' => '把已经抽出来的 UI 组件放到一个独立展示页里，后续做视觉回归和拆分都会更直接。',
            'authorName' => 'Lagos Studio',
            'authorHandle' => 'lagosstudio',
            'authorAvatarUrl' => null,
            'posterUrl' => 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?auto=format&fit=crop&w=900&q=80',
            'videoUrl' => 'https://example.com/showcase.mp4',
            'hlsUrl' => 'https://example.com/showcase.m3u8',
            'durationText' => '0:42',
            'mediaWidth' => 1080,
            'mediaHeight' => 1350,
            'postedAt' => now()->subHours(5)->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePendingTweet(): array
    {
        return [
            'tweetId' => 'ui-preview-pending-1002',
            'status' => 'pending',
            'text' => '还未完成解析时，卡片会保留作者和文案信息，媒体区展示封面占位。',
            'authorName' => 'Scene Draft',
            'authorHandle' => 'scenedraft',
            'authorAvatarUrl' => null,
            'posterUrl' => 'https://images.unsplash.com/photo-1516321497487-e288fb19713f?auto=format&fit=crop&w=900&q=80',
            'videoUrl' => null,
            'hlsUrl' => null,
            'durationText' => '',
            'mediaWidth' => 1080,
            'mediaHeight' => 1350,
            'postedAt' => now()->subMinutes(42)->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleWideTweet(): array
    {
        return [
            'tweetId' => 'ui-preview-wide-1003',
            'status' => 'resolved',
            'text' => '横向画幅的媒体区可以直接复用同一个组件，只切换 frame class。',
            'authorName' => 'Lagos Studio',
            'authorHandle' => 'lagosstudio',
            'authorAvatarUrl' => null,
            'posterUrl' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80',
            'videoUrl' => 'https://example.com/showcase-wide.mp4',
            'hlsUrl' => null,
            'durationText' => '2:08',
            'mediaWidth' => 1280,
            'mediaHeight' => 960,
            'postedAt' => now()->subDays(1)->toISOString(),
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
