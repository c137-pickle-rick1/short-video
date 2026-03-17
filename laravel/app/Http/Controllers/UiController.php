<?php

namespace App\Http\Controllers;

use App\ShortVideo\View\UiPageRenderer;
use Illuminate\Contracts\View\View;

final class UiController extends Controller
{
    public function __invoke(UiPageRenderer $renderer): View
    {
        return view('shortvideo.ui', [
            'documentHead' => $renderer->renderDocumentHead('UI Components | Lagos Explore Feed'),
            'pageHeader' => $renderer->renderPageHeader(route('login')),
            'showcaseContent' => $renderer->renderShowcase(),
        ]);
    }
}
