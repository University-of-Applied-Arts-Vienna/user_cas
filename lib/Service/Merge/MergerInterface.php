<?php


namespace OCA\UserCAS\Service\Merge;


/**
 * Interface MergerInterface
 * @package OCA\UserCAS\Merger
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
interface MergerInterface
{

    public function mergeUsers(array &$userStack, array $userToMerge);
}