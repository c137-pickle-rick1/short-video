<section class="w-full max-w-md rounded-[32px] border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
  <h1 class="text-3xl font-semibold tracking-tight text-stone-950">{{ $title }}</h1>
  <p id="login-placeholder-note" class="mt-3 text-sm leading-6 text-stone-500">
    {{ $note }}
  </p>

  @if($statusMessage !== null && trim($statusMessage) !== '')
    <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ $statusMessage }}</div>
  @endif

  @if($errorMessage !== null && trim($errorMessage) !== '')
    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errorMessage }}</div>
  @endif

  <form method="{{ $formMethod }}" action="{{ $formAction }}" class="mt-8 grid gap-4" aria-describedby="login-placeholder-note">
    {!! $hiddenFieldsMarkup !!}
    {!! $fieldsMarkup !!}
    {!! $actionMarkup !!}
  </form>
</section>
