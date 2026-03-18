@php
  $video = is_array($video ?? null) ? $video : [];
  $engagement = is_array($video['engagement'] ?? null) ? $video['engagement'] : [];
  $comments = is_array($video['comments'] ?? null) ? $video['comments'] : [];
  $followState = is_array($video['followState'] ?? null) ? $video['followState'] : [];
  $playerSources = is_array($video['playerSources'] ?? null) ? $video['playerSources'] : [];
  $structuredData = is_array($video['structuredData'] ?? null) ? $video['structuredData'] : [];
  $twitterCard = !empty($video['ogImageUrl']) ? 'summary_large_image' : 'summary';
  $authorHandleClass = !empty($video['authorHandle']) ? 'mt-1 truncate text-sm text-gray-500' : null;
  $likeCount = (int) ($engagement['likeCount'] ?? 0);
  $bookmarkCount = (int) ($engagement['bookmarkCount'] ?? 0);
  $commentCount = (int) ($engagement['commentCount'] ?? 0);
  $viewCount = (int) ($engagement['viewCount'] ?? 0);
  $likedByViewer = ($engagement['likedByViewer'] ?? false) === true;
  $bookmarkedByViewer = ($engagement['bookmarkedByViewer'] ?? false) === true;
  $canComment = ($video['canComment'] ?? false) === true;
  $commentComposerPlaceholder = (string) ($video['commentComposerPlaceholder'] ?? '说点什么...');
  $interactionHint = (string) ($video['interactionHint'] ?? '');
  $followCreator = is_array($followState['creator'] ?? null) ? $followState['creator'] : [];
  $followAuthorUserId = isset($followCreator['userId']) && is_int($followCreator['userId']) ? $followCreator['userId'] : null;
  $followViewerUserId = isset($followState['viewerUserId']) && is_int($followState['viewerUserId']) ? $followState['viewerUserId'] : null;
  $canFollowAuthor = ($followState['canFollowCreator'] ?? false) === true;
  $followedByViewer = ($followState['followedByViewer'] ?? false) === true;
  $followButtonBaseClass = 'inline-flex h-11 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold shadow-sm transition';
  $videoBackUrl = url()->previous();
  if (!is_string($videoBackUrl) || trim($videoBackUrl) === '' || $videoBackUrl === request()->fullUrl()) {
      $videoBackUrl = route('home');
  }
@endphp

<x-shortvideo.layout.app-shell
  :shell="$shell"
  body-class="overflow-x-hidden bg-stone-50 text-gray-900 antialiased"
>
  <x-slot:headExtra>
    <meta name="description" content="{{ $video['metaDescription'] ?? '' }}" />
    <link rel="canonical" href="{{ $video['canonicalUrl'] ?? '' }}" />
    <meta property="og:type" content="video.other" />
    <meta property="og:site_name" content="Lagos Explore Feed" />
    <meta property="og:title" content="{{ $video['title'] ?? '' }}" />
    <meta property="og:description" content="{{ $video['metaDescription'] ?? '' }}" />
    <meta property="og:url" content="{{ $video['canonicalUrl'] ?? '' }}" />
    @if(!empty($video['ogImageUrl']))
      <meta property="og:image" content="{{ $video['ogImageUrl'] }}" />
      <meta property="og:image:alt" content="{{ $video['title'] ?? '' }}" />
    @endif
    @if(!empty($video['contentUrl']))
      <meta property="og:video" content="{{ $video['contentUrl'] }}" />
    @endif
    <meta name="twitter:card" content="{{ $twitterCard }}" />
    <meta name="twitter:title" content="{{ $video['title'] ?? '' }}" />
    <meta name="twitter:description" content="{{ $video['metaDescription'] ?? '' }}" />
    @if(!empty($video['ogImageUrl']))
      <meta name="twitter:image" content="{{ $video['ogImageUrl'] }}" />
    @endif
    <script id="video-structured-data" type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
  </x-slot:headExtra>

  <div class="grid gap-5 lg:gap-6 xl:gap-7">
    <x-shortvideo.layout.navigation-bar
      title="视频详情"
      container-class="relative -mx-3 flex h-12 items-center justify-center border-y border-gray-200 bg-white px-1 shadow-none sm:-mx-4 lg:hidden"
      title-wrapper-class="min-w-0 px-12 text-center"
      title-class="truncate text-sm font-semibold tracking-[0.08em] text-gray-950"
      :leading-action="[
        'tag' => 'a',
        'href' => $videoBackUrl,
        'iconClass' => 'ph ph-arrow-left',
        'label' => '返回上一页',
        'attributes' => [
          'data-video-detail-back' => 'true',
          'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-700 transition hover:text-gray-950',
        ],
      ]"
    />

    <article data-video-detail-page="true" class="grid gap-5 lg:gap-6">
      <nav aria-label="Breadcrumb" class="hidden flex-wrap items-center gap-2 text-sm font-medium text-gray-500 lg:flex">
        <a href="{{ route('home') }}" class="transition hover:text-gray-900">精选</a>
        <span aria-hidden="true">/</span>
        @if(!empty($video['authorProfileUrl']))
          <a href="{{ $video['authorProfileUrl'] }}" class="transition hover:text-gray-900">{{ $video['authorName'] ?? '' }}</a>
          <span aria-hidden="true">/</span>
        @endif
        <span class="max-w-full truncate text-gray-900">{{ $video['title'] ?? '' }}</span>
      </nav>

      <section class="overflow-hidden rounded-[32px] border border-gray-200 bg-white shadow-sm">
        <div class="grid xl:grid-cols-[minmax(0,1fr)_430px]">
          <div class="border-b border-gray-200 bg-black xl:border-b-0 xl:border-r xl:border-gray-200">
            <div class="flex min-h-[20rem] items-center justify-center px-4 py-4 sm:min-h-[26rem] sm:px-6 sm:py-6 xl:min-h-[42rem]">
              @if($playerSources !== [])
                <video
                  data-video-detail-player="true"
                  class="max-h-[72vh] w-full bg-black object-contain shadow-[0_28px_80px_rgba(0,0,0,0.42)] sm:max-h-[76vh]"
                  controls
                  playsinline
                  preload="metadata"
                  @if(!empty($video['posterUrl'])) poster="{{ $video['posterUrl'] }}" @endif
                >
                  @foreach($playerSources as $source)
                    <source
                      src="{{ $source['src'] ?? '' }}"
                      @if(!empty($source['type'])) type="{{ $source['type'] }}" @endif
                    />
                  @endforeach
                </video>
              @else
                <div
                  data-video-detail-player="true"
                  class="flex min-h-[20rem] w-full items-center justify-center px-6 py-10 text-center text-sm font-medium text-white/70"
                >
                  这个视频当前没有可用的播放地址。
                </div>
              @endif
            </div>
          </div>

          <aside class="flex w-full max-w-full flex-col bg-white">
            <div class="border-b border-gray-200 px-5 py-4 sm:px-6">
              <div class="flex items-start justify-between gap-4">
                <x-shortvideo.feed.author-identity
                  :image-url="$video['authorAvatarUrl'] ?? null"
                  :author-name="(string) ($video['authorName'] ?? '视频作者')"
                  :author-handle="(string) ($video['authorHandle'] ?? '')"
                  :author-initial="(string) ($video['authorInitial'] ?? 'L')"
                  avatar-size-class="h-12 w-12"
                  name-class="truncate text-base font-semibold text-gray-950"
                  :handle-class="$authorHandleClass"
                  wrapper-class="flex min-w-0 items-center gap-3"
                  fallback-class="bg-gray-900 text-white"
                  :profile-url="$video['authorProfileUrl'] ?? null"
                />

                @if($followViewerUserId === null && $followAuthorUserId !== null)
                  <a
                    href="{{ $loginUrl }}"
                    data-auth-modal-trigger="true"
                    data-auth-modal-panel="login"
                    class="{{ $followButtonBaseClass }} bg-gray-900 text-white hover:bg-gray-800"
                  >
                    关注
                  </a>
                @elseif($canFollowAuthor && $followAuthorUserId !== null)
                  <button
                    type="button"
                    class="{{ $followButtonBaseClass }} {{ $followedByViewer ? 'border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-rose-500 text-white hover:bg-rose-600' }}"
                    data-author-follow-button="true"
                    data-author-user-id="{{ $followAuthorUserId }}"
                    data-base-class="{{ $followButtonBaseClass }}"
                    data-following="{{ $followedByViewer ? 'true' : 'false' }}"
                    data-enabled="true"
                    data-loading="false"
                    data-label-follow="关注"
                    data-label-following="已关注"
                    data-label-disabled="暂不可关注"
                    data-reload-on-success="false"
                    aria-pressed="{{ $followedByViewer ? 'true' : 'false' }}"
                  >
                    {{ $followedByViewer ? '已关注' : '关注' }}
                  </button>
                @else
                  <button
                    type="button"
                    class="{{ $followButtonBaseClass }} bg-gray-100 text-gray-400"
                    disabled
                  >
                    {{ $followViewerUserId !== null && $followAuthorUserId !== null && $followViewerUserId === $followAuthorUserId ? '你自己' : '暂不可关注' }}
                  </button>
                @endif
              </div>
            </div>

            <div class="flex-1 px-5 py-5 sm:px-6 sm:py-6">
              <h1 class="text-[1.2rem] font-semibold leading-[1.42] text-gray-950 sm:text-[1.35rem]">
                {{ $video['title'] ?? '' }}
              </h1>

              <p class="mt-3 text-sm font-normal tracking-[0.02em] text-gray-500">
                发布日期 · {{ $video['publishedAtDetailText'] ?? $video['publishedAtText'] ?? '发布日期待更新' }}
              </p>

              <div data-video-detail-meta="true" class="mt-4 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ $viewCount }} 次观看</span>
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ $commentCount }} 条评论</span>
              </div>

              <div class="my-5 h-px bg-gray-200"></div>

              <section aria-labelledby="detail-comments-title">
                <div class="flex items-end justify-between gap-3">
                  <div>
                    <h2 id="detail-comments-title" class="text-base font-semibold text-gray-950">
                      评论区
                    </h2>
                  </div>
                  <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
                    {{ $video['commentsStatusText'] ?? '暂无评论' }}
                  </span>
                </div>

                <div class="mt-5 grid gap-5">
                  @forelse($comments as $comment)
                    @php
                      $commentAuthor = is_array($comment['author'] ?? null) ? $comment['author'] : [];
                      $commentAuthorName = (string) ($commentAuthor['name'] ?? '匿名用户');
                    @endphp
                    <article class="grid gap-3">
                      <div class="flex items-start gap-3">
                        <x-ui.avatar
                          :image-url="$commentAuthor['avatarUrl'] ?? null"
                          :label="$commentAuthorName"
                          :initial="(string) ($commentAuthor['initial'] ?? 'L')"
                          size-class="h-10 w-10"
                          fallback-class="bg-gray-100 text-gray-700"
                          image-class=""
                        />
                        <div class="min-w-0 flex-1">
                          <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $commentAuthorName }}</p>
                            @if(!empty($commentAuthor['username']))
                              <p class="mt-1 truncate text-xs text-gray-400">&#64;{{ $commentAuthor['username'] }}</p>
                            @endif
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $comment['body'] ?? '' }}</p>
                          </div>
                          <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-400">
                            <span>{{ $comment['createdAtText'] ?? '刚刚' }}</span>
                          </div>
                        </div>
                      </div>
                    </article>
                  @empty
                    <article class="rounded-3xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm leading-6 text-gray-500">
                      还没有评论，抢先说点什么。
                    </article>
                  @endforelse
                </div>
              </section>
            </div>

            <div class="border-t border-gray-200 px-5 py-4 sm:px-6">
              <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                  <div class="flex shrink-0 items-center gap-2">
                    <span class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold {{ $likedByViewer ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-gray-200 bg-white text-gray-500' }}">
                      <i class="{{ $likedByViewer ? 'ph-fill ph-heart' : 'ph ph-heart' }} text-[1.05rem] leading-none" aria-hidden="true"></i>
                      <span class="text-xs font-semibold tabular-nums opacity-80">{{ $likeCount }}</span>
                    </span>

                    <span class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border px-4 text-sm font-semibold {{ $bookmarkedByViewer ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-gray-200 bg-white text-gray-500' }}">
                      <i class="{{ $bookmarkedByViewer ? 'ph-fill ph-bookmark-simple' : 'ph ph-bookmark-simple' }} text-[1.05rem] leading-none" aria-hidden="true"></i>
                      <span class="text-xs font-semibold tabular-nums opacity-80">{{ $bookmarkCount }}</span>
                    </span>

                    <span class="inline-flex h-11 shrink-0 items-center gap-2.5 rounded-full border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-500">
                      <i class="ph ph-chat-circle text-[1.05rem] leading-none" aria-hidden="true"></i>
                      <span class="text-xs font-semibold tabular-nums opacity-80">{{ $commentCount }}</span>
                    </span>
                  </div>

                  <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div class="flex h-12 min-w-0 flex-1 items-center rounded-full bg-gray-100 px-4 text-sm text-gray-400">
                      {{ $commentComposerPlaceholder }}
                    </div>

                    @if(!$canComment)
                      <a
                        href="{{ $loginUrl }}"
                        data-auth-modal-trigger="true"
                        data-auth-modal-panel="login"
                        class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white transition hover:bg-gray-800"
                      >
                        登录评论
                      </a>
                    @else
                      <button
                        type="button"
                        class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-gray-900 px-5 text-sm font-semibold text-white/70"
                        disabled
                      >
                        发送
                      </button>
                    @endif
                  </div>
                </div>

                @if($interactionHint !== '')
                  <p class="text-xs leading-5 text-gray-400">{{ $interactionHint }}</p>
                @endif
              </div>
            </div>
          </aside>
        </div>
      </section>
    </article>
  </div>

  @vite('laravel/resources/js/features/social/follow-buttons.js')
</x-shortvideo.layout.app-shell>
