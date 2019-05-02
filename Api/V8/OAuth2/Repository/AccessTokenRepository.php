<?php
namespace Api\V8\OAuth2\Repository;

use Api\V8\BeanDecorator\BeanManager;
use Api\V8\OAuth2\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    /**
     * @var AccessTokenEntity
     */
    private $accessTokenEntity;

    /**
     * @var BeanManager
     */
    private $beanManager;

    /**
     * @param AccessTokenEntity $accessTokenEntity
     * @param BeanManager $beanManager
     */
    public function __construct(AccessTokenEntity $accessTokenEntity, BeanManager $beanManager)
    {
        $this->accessTokenEntity = $accessTokenEntity;
        $this->beanManager = $beanManager;
    }

    /**
     * @inheritdoc
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $this->accessTokenEntity->setClient($clientEntity);

        // we keep this even we don't have scopes atm
        foreach ($scopes as $scope) {
            $this->accessTokenEntity->addScope($scope);
        }

        $this->accessTokenEntity->setUserIdentifier($userIdentifier);

        return $this->accessTokenEntity;
    }

    /**
     * @inheritdoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $clientId = $accessTokenEntity->getClient()->getIdentifier();

        /** @var \User $user */
        $client = $this->beanManager->getBeanSafe('OAuth2Clients', $clientId);

        /** @var \User $user */
        $user = $this->beanManager->newBeanSafe('Users');

        if (!empty($_POST['username'])) {
            $user->retrieve_by_string_fields(
                ['user_name' => $_POST['username']]
            );
        }

        $userId = !empty($user->id) ? $user->id : $client->assigned_user_id;

        /** @var \OAuth2Tokens $token */
        $token = $this->beanManager->newBeanSafe(\OAuth2Tokens::class);

        $token->access_token = $accessTokenEntity->getIdentifier();

        $token->access_token_expires = $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s');

        $token->client = $clientId;

        $token->assigned_user_id = $userId;

        $token->save();
    }

    /**
     * @inheritdoc
     *
     * @throws \InvalidArgumentException When access token is not found.
     */
    public function revokeAccessToken($tokenId)
    {
        $token = $this->beanManager->newBeanSafe(\OAuth2Tokens::class);
        $token->retrieve_by_string_fields(
            ['access_token' => $tokenId]
        );

        if ($token->id === null) {
            throw new \InvalidArgumentException('Access token is not found for this client');
        }

        $token->mark_deleted($token->id);
    }

    /**
     * @inheritdoc
     */
    public function isAccessTokenRevoked($tokenId)
    {
        /** @var \OAuth2Tokens $token */
        $token = $this->beanManager->newBeanSafe(\OAuth2Tokens::class);
        $token->retrieve_by_string_fields(
            ['access_token' => $tokenId]
        );

        return $token->id === null || $token->token_is_revoked === '1' || new \DateTime() > new \DateTime($token->access_token_expires);
    }
}
