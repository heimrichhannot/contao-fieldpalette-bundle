<?php

namespace HeimrichHannot\FieldpaletteBundle\Security\Voter;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\Model;
use HeimrichHannot\FieldpaletteBundle\Model\FieldPaletteModel;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class FieldpaletteVoter implements VoterInterface
{
    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        if (!in_array(ContaoCorePermissions::DC_PREFIX.FieldpaletteModel::TABLE, $attributes, true)) {
            return self::ACCESS_ABSTAIN;
        }

        $ptable = $subject->getCurrent()['ptable'] ?? null;
        $pid = $subject->getCurrent()['pid'] ?? null;
        if (!$ptable || !$pid) {
            return self::ACCESS_ABSTAIN;
        }

        try {
            $model = Model::getClassFromTable($ptable)::findByPk($pid);
        } catch (\RuntimeException) {
            return self::ACCESS_ABSTAIN;
        }

        if (!$model) {
            return self::ACCESS_ABSTAIN;
        }

        $action = new ReadAction($ptable, $model->row());

        return $this->accessDecisionManager->decide($token, [ContaoCorePermissions::DC_PREFIX.$ptable], $action);
    }


}