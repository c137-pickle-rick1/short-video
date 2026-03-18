@php
    $creator = $creator ?? [];
    $creatorData = is_array($creator['creator'] ?? null) ? $creator['creator'] : [];
    $creatorUserId = isset($creatorData['userId']) && is_int($creatorData['userId']) ? $creatorData['userId'] : null;
    $viewerUserId = isset($creator['viewerUserId']) && is_int($creator['viewerUserId']) ? $creator['viewerUserId'] : null;
    $canFollowCreator = ($creator['canFollowCreator'] ?? false) === true;
    $followedByViewer = ($creator['followedByViewer'] ?? false) === true;
    $reloadOnSuccess = ($reloadOnSuccess ?? false) === true;
    $size = $size ?? 'default';
    $buttonBaseClass = $size === 'compact'
        ? 'inline-flex h-10 shrink-0 items-center justify-center rounded-full px-4 text-sm font-semibold shadow-sm transition'
        : 'inline-flex h-11 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold shadow-sm transition';
@endphp

@if($viewerUserId === null)
    <a
        href="{{ $loginUrl }}"
        data-auth-modal-trigger="true"
        data-auth-modal-panel="login"
        class="{{ $buttonBaseClass }} bg-gray-900 text-white hover:bg-gray-800"
    >
        关注
    </a>
@elseif(! $canFollowCreator || $creatorUserId === null)
    <button
        type="button"
        class="{{ $buttonBaseClass }} bg-gray-100 text-gray-400"
        disabled
    >
        暂不可关注
    </button>
@else
    <button
        type="button"
        class="{{ $buttonBaseClass }} {{ $followedByViewer ? 'border border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-rose-500 text-white hover:bg-rose-600' }}"
        data-author-follow-button="true"
        data-author-user-id="{{ $creatorUserId }}"
        data-base-class="{{ $buttonBaseClass }}"
        data-following="{{ $followedByViewer ? 'true' : 'false' }}"
        data-enabled="true"
        data-loading="false"
        data-label-follow="关注"
        data-label-following="已关注"
        data-label-disabled="暂不可关注"
        data-reload-on-success="{{ $reloadOnSuccess ? 'true' : 'false' }}"
        aria-pressed="{{ $followedByViewer ? 'true' : 'false' }}"
    >
        {{ $followedByViewer ? '已关注' : '关注' }}
    </button>
@endif
