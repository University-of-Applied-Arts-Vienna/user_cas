<?php


namespace OCA\UserCAS\Service\Merge;


/**
 * Class AdUserMerger
 * @package OCA\UserCAS\Service\Merge
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
class AdUserMerger implements MergerInterface
{

    /**
     * Merge users method
     *
     * @param array $userStack
     * @param array $userToMerge
     */
    public function mergeUsers(array &$userStack, array $userToMerge)
    {
        # User already in stack
        if (isset($userStack[$userToMerge["uid"]])) {

            $foo = "";

            //TODO: compare users and select the account to use
            //TODO: Check if accounts are enabled or disabled
            //      if both disabled, account stays disabled
            //      if one is enabled, use the information of this one

            //TODO: if both enabled, use information of cn=p*

        } else { # User not in stack

            $userStack[$userToMerge["uid"]] = $userToMerge;
        }
    }
}