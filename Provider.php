<?php

namespace SocialiteProviders\TikTokAdsApi;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\ProviderInterface;
use Nette\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    public const IDENTIFIER = 'TIKTOKADSAPI';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://ads.tiktok.com/marketing_api/auth', $state);
    }

    protected function buildAuthUrlFromBase($url, $state)
    {
        return $url.'?'.http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @param  string|null  $state
     * @return array
     */
    protected function getCodeFields($state = null)
    {
        $fields = [
            'app_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
        ];

        if ($this->usesState()) {
            $fields['state'] = $state;
        }

        if ($this->usesPKCE()) {
            $fields['code_challenge'] = $this->getCodeChallenge();
            $fields['code_challenge_method'] = $this->getCodeChallengeMethod();
        }

        return array_merge($fields, $this->parameters);
    }


    /**
     * {@inheritdoc}
     */
    protected function getCode()
    {
        return $this->request->input('auth_code');
    }


    /**
     * {@inheritdoc}
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        // see https://ads.tiktok.com/marketing_api/docs?id=1701890914536450
        $response = $this->getAccessTokenResponse($this->getCode());
        $token = Arr::get($response, 'data.access_token');
        $scopes = Arr::get($response, 'data.scope');

        $advertiserIds = Arr::get($response, 'data.advertiser_ids');


        $this->user = $this->mapUserToObject(
            $this->getUserByToken($token)
        );
        $this->user->setToken($token);
        $this->user->setApprovedScopes($scopes);
        $this->user->setRaw( ['advertiser_ids'   => $advertiserIds]);

        return $this->user;
    }


    /**
     * Get TikTok user by token.
     *
     * @param $token
     * @return mixed
     * @see https://ads.tiktok.com/marketing_api/docs?id=100680
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://ads.tiktok.com/open_api/v1.2/user/info/', [
            RequestOptions::HEADERS => [
                'Access-Token' => $token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     * https://ads.tiktok.com/marketing_api/docs?id=1701890914536450
     */
    protected function getTokenUrl()
    {
        return 'https://business-api.tiktok.com/open_api/v1.2/oauth2/access_token/';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);


        $data = json_decode($response->getBody(), true);

        return Arr::add($data, 'expires_in', Arr::pull($data, 'expires'));
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => Arr::get($user, 'data.id'),
            'name'     => Arr::get($user, 'data.display_name'),
            'email'     => Arr::get($user, 'data.email'),
        ]);
    }


    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($authCode)
    {
        $fields = [
            'grant_type' => 'authorization_code',
            'app_id' => $this->clientId,
            'secret' => $this->clientSecret,
            'auth_code' => $authCode,
        ];

        if ($this->usesPKCE()) {
            $fields['code_verifier'] = $this->request->session()->pull('code_verifier');
        }

        return $fields;
    }
}
