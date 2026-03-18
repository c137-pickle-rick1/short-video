<section class="feed-grid" id="feed-grid" aria-live="polite" data-empty="{{ $isEmpty ? 'true' : 'false' }}">
  <div class="feed-grid-col">
    {!! $itemsMarkup !!}
  </div>
  <div class="feed-grid-col"></div>
  <div class="feed-grid-col hidden xl:block"></div>
  <div class="feed-grid-col hidden 2xl:block"></div>
</section>
<div id="feed-sentinel" class="h-px" aria-hidden="true"></div>
@include('shortvideo.partials.feed.loading-indicator')
