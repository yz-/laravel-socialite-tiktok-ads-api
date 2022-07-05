<?php

namespace SocialiteProviders\TikTokAdsApi;

use SocialiteProviders\Manager\SocialiteWasCalled;

class TikTokAdsApiExtendSocialite
{
    /**
     * Register the provider.
     *
     * @param \SocialiteProviders\Manager\SocialiteWasCalled $socialiteWasCalled
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('tiktok_ads_api', Provider::class);
    }
}
