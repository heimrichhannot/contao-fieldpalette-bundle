<?php

namespace HeimrichHannot\FieldpaletteBundle\Security\Voter;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
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
        // check if we are in contao 5 context
        if (!class_exists(ReadAction::class)) {
            return self::ACCESS_ABSTAIN;
        }

        if (!in_array(ContaoCorePermissions::DC_PREFIX.FieldpaletteModel::TABLE, $attributes, true)) {
            return self::ACCESS_ABSTAIN;
        }

        $row = match ($subject::class) {
            CreateAction::class => $subject->getNew(),
            ReadAction::class => $subject->getCurrent(),
            UpdateAction::class => $subject->getCurrent(),
            DeleteAction::class => $subject->getCurrent(),
            default => null,
        };

        if (!$row || !is_array($row)) {
            return self::ACCESS_ABSTAIN;
        }

        $ptable = $row['ptable'] ?? null;
        $pid = $row['pid'] ?? null;
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