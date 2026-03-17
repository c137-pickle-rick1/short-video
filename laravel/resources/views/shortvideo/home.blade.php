<!doctype html>
<html lang="zh-CN">
  <head>
{!! $documentHead !!}
  </head>
  <body class="overflow-x-hidden bg-white text-gray-900 antialiased">
    <main class="relative z-10">
      <header class="fixed inset-x-0 top-0 z-40 w-full border-b border-gray-200 bg-white/95 backdrop-blur-xl">
        <div class="mx-auto flex w-full max-w-screen-2xl items-center gap-3 px-3 py-3 sm:gap-4 sm:px-4 sm:py-4 lg:gap-5 lg:px-5 lg:py-4 xl:gap-6 xl:px-6 xl:py-4 2xl:gap-7 2xl:px-7 2xl:py-4">
          <div class="shrink-0">
            <div class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-gray-100 sm:h-12 sm:w-12">
              <span class="h-2.5 w-2.5 rounded-full bg-gray-900"></span>
            </div>
          </div>
          <label
            class="mx-auto flex h-11 w-full min-w-0 max-w-[480px] items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 sm:h-12 sm:gap-3 sm:px-4"
          >
            <i class="ph ph-magnifying-glass text-base text-gray-500" aria-hidden="true"></i>
            <input
              type="search"
              placeholder="搜索视频、作者或关键词"
              class="min-w-0 flex-1 bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400"
            />
          </label>
          <button
            type="button"
            aria-label="切换语言"
            title="切换语言"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 text-lg text-gray-600 transition hover:text-gray-900 sm:h-12 sm:w-12"
          >
            <i class="ph ph-globe text-xl leading-none" aria-hidden="true"></i>
          </button>
        </div>
      </header>

      <div class="h-[68px] sm:h-20" aria-hidden="true"></div>

      <div class="mx-auto w-full max-w-screen-2xl">
        <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 lg:gap-5 lg:p-5 xl:gap-6 xl:p-6 2xl:gap-7 2xl:p-7 lg:flex-row lg:items-start">
{!! $desktopNavigation !!}
{!! $mobileNavigation !!}
          <section class="min-w-0 flex-1">
{!! $feedGrid !!}
          </section>
        </div>
        <div class="h-24 lg:hidden" aria-hidden="true"></div>
      </div>
    </main>

    <template id="empty-state-template">
      {!! $emptyStateMarkup !!}
    </template>

{!! $detailModalMarkup !!}

    <script id="feed-bootstrap" type="application/json">{!! $bootstrapData !!}</script>
    <script src="/vendor/plyr/plyr.min.js"></script>
    <script src="/vendor/colcade/colcade.js"></script>
    <script src="/vendor/hls/hls.min.js"></script>
    <script type="module" src="/app.js"></script>
  </body>
</html>
