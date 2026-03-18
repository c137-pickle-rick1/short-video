<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\ShortVideo\Services\VideoDetailPageService;
use App\ShortVideo\View\ShortVideoPageViewFactory;
use Illuminate\Contracts\View\View;

final class VideoController extends Controller
{
    public function __invoke(
        Video $video,
        VideoDetailPageService $videoDetailPages,
        ShortVideoPageViewFactory $pages
    ): View {
        return $pages->renderVideoDetailPage($videoDetailPages->getPageViewModel($video));
    }
}
