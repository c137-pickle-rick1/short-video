@include('shortvideo.partials.foundation.empty-state', [
  'iconClass' => $iconClass ?? 'ph ph-magnifying-glass',
  'title' => $title,
  'description' => $description,
  'buttonLabel' => $buttonLabel ?? null,
  'buttonHref' => $buttonHref ?? null,
  'buttonAttributes' => $buttonAttributes ?? [],
  'containerClass' => 'feed-grid-item mb-3 inline-flex min-h-[clamp(26rem,58vh,42rem)] w-full flex-col items-center justify-center px-6 py-12 text-center sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7',
  'dataEmptyState' => true,
])
