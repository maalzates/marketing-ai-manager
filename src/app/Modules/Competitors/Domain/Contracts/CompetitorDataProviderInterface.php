<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Contracts;

use App\Modules\Competitors\Application\DTO\CompetitorCommentDTO;
use App\Modules\Competitors\Application\DTO\CompetitorPostDTO;
use App\Modules\Competitors\Application\DTO\FetchAdsDTO;
use App\Modules\Competitors\Application\DTO\FetchCommentsDTO;
use App\Modules\Competitors\Application\DTO\FetchPostsDTO;
use App\Modules\Competitors\Application\DTO\ProviderRunResultDTO;

/**
 * The seam between this module and whoever scrapes. Nothing above it knows an actor id, a
 * run status or a provider field name, so replacing Apify with a self-hosted scraper is
 * one new implementation and zero changes in the domain.
 */
interface CompetitorDataProviderInterface
{
    /** @return ProviderRunResultDTO<CompetitorPostDTO> */
    public function fetchPosts(FetchPostsDTO $dto): ProviderRunResultDTO;

    /** @return ProviderRunResultDTO<CompetitorCommentDTO> */
    public function fetchComments(FetchCommentsDTO $dto): ProviderRunResultDTO;

    /** @return ProviderRunResultDTO<CompetitorPostDTO> */
    public function fetchAds(FetchAdsDTO $dto): ProviderRunResultDTO;
}
