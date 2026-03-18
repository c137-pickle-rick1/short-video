<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
@if($includeCsrfToken ?? false)
  <meta name="csrf-token" content="{{ csrf_token() }}" />
@endif
<title>{{ $pageTitle }}</title>
<link rel="stylesheet" href="/vendor/fonts/fonts.css" />
@if($includePhosphorStyles ?? false)
  <link rel="stylesheet" href="/vendor/phosphor/regular/style.css" />
  <link rel="stylesheet" href="/vendor/phosphor/fill/style.css" />
@endif
@if($includePlyrStyles ?? false)
  <link rel="stylesheet" href="/vendor/plyr/plyr.css" />
@endif
<link rel="stylesheet" href="/styles.css" />
