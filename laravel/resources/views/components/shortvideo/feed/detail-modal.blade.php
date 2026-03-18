@props([])

<div
  id="feed-detail-modal"
  class="fixed inset-0 z-[80] hidden flex items-stretch justify-stretch bg-black p-0 lg:items-center lg:justify-center lg:bg-black/20 lg:p-3 xl:p-7"
  hidden
>
  <section
    id="feed-detail-modal-panel"
    class="relative z-10 flex h-[100dvh] w-full overflow-hidden bg-black lg:h-[92vh] lg:max-h-[920px] lg:max-w-[1520px] lg:rounded-[32px] lg:bg-white lg:shadow-glass animate-card-in"
    role="dialog"
    aria-modal="true"
    aria-labelledby="detail-modal-title"
    tabindex="-1"
  >
  </section>
</div>
