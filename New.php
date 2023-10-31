<?php

namespace App\Repositories;

use App\Models\SocialAccount;
use App\Models\FbPage;
use App\Entities\SocialAccountEntity;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use Illuminate\Support\Arr;

class SocialAccountRepository
{
    /**
     * social account instance
     *
     * @var App\Models\SocialAccount
     */
    private $model;

    private $fbPage;

    public function __construct(SocialAccount $model, FbPage $fbPage)
    {
        $this->model = $model;
        $this->fbPage = $fbPage;
    }
        public function disconnect(SocialAccountEntity $entity)
    {
        $id = $entity->getId();
        $builder = $this->model->where('channel', $entity->getChannel())
            ->where('service', $entity->getService());

        if (!empty($entity->getOrgId()) && $entity->isUnlimitedAccess()) {
            $builder->where('org_id', $entity->getOrgId());
        } else if (!empty($entity->getOrgId()) && !$entity->isUnlimitedAccess()) {
            $builder->where('org_id', $entity->getOrgId())
                ->where('kw_uid', $entity->getKwuid());
        } else {
            $builder->where('kw_uid', $entity->getKwuid())
                ->whereNull('org_id');
        }

        if($entity->getChannel() == config('global.channel_office365')) {
            /*if(!$builder->first()) {
                return true;
            }*/
            $this->deleteUserMetas($builder);
        }
        if($id) {
            $builder = $builder->where('id', $id);
        }

        $socialAccount = $builder->get();
        $account = Arr::collapse($socialAccount->toArray());
        if($accountId = Arr::get($account, 'id')) {
            $pages = $this->fbPage->where('account_id', $accountId)->get();
            foreach ($pages as $page) {
                $page->delete();
            }
        }

        if($socialAccount->isEmpty()) {
            $errorMessage = !empty($entity->getOrgId()) && !$entity->isUnlimitedAccess() ?
                trans('messages.restricted_access') :
                trans('messages.social_profile_not_exists');
            throw new Exception($errorMessage, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if($id) {
            $account = $builder->first();
            return $account->delete();
        }

        $res = $builder->delete();

        return true;
    }

}