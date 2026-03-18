<div class="mx-auto w-full max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8 xl:px-10">
  <article class="mx-auto min-w-0 max-w-[980px]">
    {!! $pageIntro !!}
    <div class="mt-12 grid gap-16">
      @foreach($sections as $section)
        {!! $section !!}
      @endforeach
    </div>
  </article>
</div>
