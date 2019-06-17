<?php


namespace OCA\UserCAS\Service\Import;

use OCA\UserCAS\Service\Merge\AdUserMerger;
use OCA\UserCAS\Service\Merge\MergerInterface;
use OCP\IConfig;
use Psr\Log\LoggerInterface;


/**
 * Class AdImporter
 * @package OCA\UserCAS\Service\Import
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
class AdImporter implements ImporterInterface
{

    /**
     * @var boolean|resource
     */
    private $ldapConnection;

    /**
     * @var MergerInterface $merger
     */
    private $merger;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var IConfig
     */
    private $config;


    /**
     * AdImporter constructor.
     * @param IConfig $config
     */
    public function __construct(IConfig $config)
    {

        $this->config = $config;
    }


    /**
     * @param LoggerInterface $logger
     *
     * @throws \Exception
     */
    public function init(LoggerInterface $logger)
    {

        $this->merger = new AdUserMerger();
        $this->logger = $logger;

        $this->ldapConnect();
        $this->ldapBind();

        $this->logger->info("Init complete.");
    }

    /**
     * @throws \Exception
     */
    public function close()
    {

        $this->ldapClose();
    }

    /**
     * Get User data
     *
     * @return array User data
     */
    public function getUsers()
    {

        # Get all needed attributes from env
        //TODO: Replace all getenv calls with $this->config->getAppValue($this->appName, ''); calls

        $uidAttribute = getenv("MAP_UID");
        $displayNameAttribute1 = getenv("MAP_DISPLAY_NAME_1");
        $displayNameAttribute2 = getenv("MAP_DISPLAY_NAME_2");
        $emailAttribute = getenv("MAP_EMAIL");
        $groupsAttribute = getenv("MAP_GROUPS");
        $quotaAttribute = getenv("MAP_QUOTA");
        $enableAttribute = getenv("MAP_ENABLE");

        $keep = [$uidAttribute, $displayNameAttribute1, $displayNameAttribute2, $emailAttribute, $groupsAttribute, $quotaAttribute, $enableAttribute]; // [getenv("MAP_UID"), explode(",", getenv("MAP_DISPLAY_NAME")), getenv("MAP_EMAIL"), getenv("MAP_GROUPS"), getenv("MAP_QUOTA"), getenv("MAP_ENABLE")]

        $groupAttrField = getenv("MAP_GROUPS_FIELD");
        $groupsKeep = [$groupAttrField];

        $pageSize = intval(getenv("AD_SYNC_PAGESIZE"));

        $users = [];

        $this->logger->info("Getting all users from the AD …");

        # Get all members of the sync group
        $memberPages = $this->getLdapList(getenv("AD_BASE_DN"), getenv("AD_SYNC_FILTER"), $keep, $pageSize);

        foreach ($memberPages as $memberPage) {

            #var_dump($memberPage["count"]);

            for ($key = 0; $key < $memberPage["count"]; $key++) {

                $m = $memberPage[$key];

                #var_dump($m["sn"][0]);

                # Each attribute is returned as an array, the first key is [count], [0]+ will contain the actual value(s)
                $employeeID = isset($m[$uidAttribute][0]) ? $m[$uidAttribute][0] : "";
                $mail = isset($m[$emailAttribute][0]) ? $m[$emailAttribute][0] : "";

                $displayName = $employeeID;

                if (isset($m[$displayNameAttribute1][0])) {

                    $displayName = $m[$displayNameAttribute1][0];

                    if (isset($m[$displayNameAttribute2][0])) {

                        $displayName .= getenv("MAP_DISPLAY_NAME_DELIMITER") . $m[$displayNameAttribute2][0];
                    }
                } else {

                    if (isset($m[$displayNameAttribute2][0])) {

                        $displayName = $m[$displayNameAttribute2][0];
                    }
                }

                $quota = isset($m[$quotaAttribute][0]) ? intval($m[$quotaAttribute][0]) : 0;
                $enable = isset($m[$enableAttribute][0]) ? intval($m[$enableAttribute][0]) : 0;

                $groupsArray = [];

                $addUser = FALSE;

                if (isset($m[$groupsAttribute][0])) {

                    # Cycle all groups of the user
                    for ($j = 0; $j < $m[$groupsAttribute]["count"]; $j++) {

                        # Check if user is in MAP_GROUPS_ADD group
                        if (isset($m[$groupsAttribute][$j])) {

                            $groupCn = $m[$groupsAttribute][$j];

                            # Retrieve the MAP_GROUPS_FIELD attribute of the group
                            $groupAttr = $this->getLdapAttributes($groupCn, $groupsKeep);
                            $groupName = '';

                            if (isset($groupAttr[$groupAttrField][0])) {

                                $addUser = TRUE; # Only add user if the group has a description field

                                $groupName = $groupAttr[$groupAttrField][0];

                                # Replace umlauts
                                if (getenv("MAP_GROUPS_REPLACE_UMLAUTS")) {

                                    $groupName = str_replace("Ä", "Ae", $groupName);
                                    $groupName = str_replace("Ö", "Oe", $groupName);
                                    $groupName = str_replace("Ü", "Ue", $groupName);
                                    $groupName = str_replace("ä", "ae", $groupName);
                                    $groupName = str_replace("ö", "oe", $groupName);
                                    $groupName = str_replace("ü", "ue", $groupName);
                                    $groupName = str_replace("ß", "ss", $groupName);
                                }

                                # Filter unwanted characters
                                $groupName = preg_replace("/[^" . getenv("MAP_GROUPS_FILTER") . "]+/", "", $groupName);
                            }

                            if (strlen($groupName) > 0) {

                                $groupsArray[] = $groupName;
                            }
                        }
                    }

                    $groupsArray = implode(" | ", $groupsArray);
                } else {

                    $groupsArray = "No " . getenv("MAP_GROUPS") . " field found.";
                }

                # Fill the users array only if we have an employeeId and addUser is true
                if (isset($employeeID) && $addUser) {

                    $this->merger->mergeUsers($users, ['uid' => $employeeID, 'displayName' => $displayName, 'email' => $mail, 'quota' => $quota, 'groups' => $groupsArray, 'enable' => $enable]);
                }
            }
        }

        #$this->exportAsCsv($users);

        $this->logger->info("Users have been retrieved.");

        return $users;
    }


    /**
     * List ldap entries in the base dn
     *
     * @param string $object_dn
     * @param $filter
     * @param array $keepAtributes
     * @param $pageSize
     * @return array
     */
    protected function getLdapList($object_dn, $filter, $keepAtributes, $pageSize)
    {

        $cookie = '';
        $members = [];

        do {

            // Query Group members
            ldap_control_paged_result($this->ldapConnection, $pageSize, false, $cookie);

            $results = ldap_search($this->ldapConnection, $object_dn, $filter, $keepAtributes/*, array("member;range=$range_start-$range_end")*/) or die('Error searching LDAP: ' . ldap_error($this->ldapConnection));
            $members[] = ldap_get_entries($this->ldapConnection, $results);

            ldap_control_paged_result_response($this->ldapConnection, $results, $cookie);

        } while ($cookie !== null && $cookie != '');

        // Return sorted member list
        sort($members);

        return $members;
    }


    /**
     * @param string $user_dn
     * @param bool $keep
     * @return array Attribute list
     */
    protected function getLdapAttributes($user_dn, $keep = false)
    {

        if (!isset($this->ldapConnection)) die('Error, no LDAP connection established');
        if (empty($user_dn)) die('Error, no LDAP user specified');

        // Disable pagination setting, not needed for individual attribute queries
        ldap_control_paged_result($this->ldapConnection, 1);

        // Query user attributes
        $results = (($keep) ? ldap_search($this->ldapConnection, $user_dn, 'cn=*', $keep) : ldap_search($this->ldapConnection, $user_dn, 'cn=*'))
        or die('Error searching LDAP: ' . ldap_error($this->ldapConnection));

        $attributes = ldap_get_entries($this->ldapConnection, $results);

        $this->logger->debug("AD attributes successfully retrieved.");

        // Return attributes list
        if (isset($attributes[0])) return $attributes[0];
        else return array();
    }


    /**
     * Connect ldap
     *
     * @return bool|resource
     * @throws \Exception
     */
    protected function ldapConnect()
    {
        try {

            $this->ldapConnection = ldap_connect("ldaps://" . getenv("AD_HOST") . ":" . intval(getenv("AD_PORT"))) or die("Could not connect to " . getenv("AD_HOST"));

            ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($this->ldapConnection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($this->ldapConnection, LDAP_OPT_NETWORK_TIMEOUT, 10);

            $this->logger->info("AD connected successfully.");

            return $this->ldapConnection;
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Bind ldap
     *
     * @throws \Exception
     */
    protected function ldapBind()
    {

        try {

            if ($this->ldapConnection) {

                $ldapIsBound = ldap_bind($this->ldapConnection, getenv("AD_USER") . getenv("AD_USER_DOMAIN"), getenv("AD_USER_PW"));

                if (!$ldapIsBound) {

                    throw new \Exception("LDAP bind failed. Error: " . ldap_error($this->ldapConnection));
                } else {

                    $this->logger->info("AD bound successfully.");
                }
            }
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Unbind ldap
     *
     * @throws \Exception
     */
    protected function ldapUnbind()
    {

        try {

            ldap_unbind($this->ldapConnection);

            $this->logger->info("AD unbound successfully.");
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Close ldap connection
     *
     * @throws \Exception
     */
    protected function ldapClose()
    {
        try {

            ldap_close($this->ldapConnection);

            $this->logger->info("AD connection closed successfully.");
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * @param $exportData
     */
    protected function exportAsCsv($exportData)
    {

        $this->logger->info("Exporting users to .csv …");

        $fp = fopen('accounts.csv', 'wa+');

        fputcsv($fp, ["UID", "displayName", "email", "quota", "groups", "enabled"]);

        foreach ($exportData as $fields) {

            fputcsv($fp, $fields);
        }

        fclose($fp);

        $this->logger->info("CSV export finished.");
    }
}