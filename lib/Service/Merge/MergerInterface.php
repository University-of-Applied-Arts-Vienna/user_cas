<?php


namespace OCA\UserCAS\Service\Merge;


/**
 * Interface MergerInterface
 * @package OCA\UserCAS\Service\Merge
 *
 * @author Original contributors
 * @copyright Original contributors
 *
 * @since 1.0.0
 */
interface MergerInterface
{

    public function mergeUsers(array &$userStack, array $userToMerge, $merge, $preferEnabledAccountsOverDisabled, $primaryAccountDnStartswWith);
}