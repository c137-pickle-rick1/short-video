@props([
  'iconClass' => 'ph ph-magnifying-glass',
  'title' => '',
  'description' => '',
  'buttonLabel' => null,
  'buttonHref' => null,
  'buttonAttributes' => [],
])

<x-ui.empty-state
  :icon-class="$iconClass"
  :title="$title"
  :description="$description"
  :button-label="$buttonLabel"
  :button-href="$buttonHref"
  :button-attributes="$buttonAttributes"
  container-class="feed-grid-item mb-3 inline-flex min-h-[clamp(26rem,58vh,42rem)] w-full flex-col items-center justify-center px-6 py-12 text-center sm:mb-4 lg:mb-5 xl:mb-6 2xl:mb-7"
  :data-empty-state="true"
/>
