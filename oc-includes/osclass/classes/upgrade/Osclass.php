<?php
/**
 * Created by Mindstellar Community.
 * User: navjottomer
 * Date: 15/07/20
 * Time: 7:03 PM
 * License is provided in root directory.
 */

namespace mindstellar\osclass\classes\upgrade;

use DBCommandClass;
use DBConnectionClass;
use mindstellar\osclass\classes\utility\FileSystem;
use mindstellar\osclass\classes\utility\Utils;
use Plugins;
use RuntimeException;

/**
 * Class Osclass
 *
 * @package mindstellar\osclass\classes\upgrade
 */
class Osclass extends UpgradePackage
{

    /**
     * Upgrade Osclass Database
     *
     * @param bool $skip_db
     *
     * @return false|string
     */
    public static function upgradeDB($skip_db = false)
    {
        set_time_limit(0);

        $error_queries = array();
        if (file_exists(osc_lib_path() . 'osclass/installer/struct.sql')) {
            $sql = file_get_contents(osc_lib_path() . 'osclass/installer/struct.sql');

            $conn = DBConnectionClass::newInstance();
            $c_db = $conn->getOsclassDb();
            $comm = new DBCommandClass($c_db);

            $error_queries = $comm->updateDB(str_replace('/*TABLE_PREFIX*/', DB_TABLE_PREFIX, $sql));
        }

        if (!$skip_db && count($error_queries[2]) > 0) {
            $skip_db_link = osc_admin_base_url(true) . '?page=upgrade&action=upgrade-funcs&skipdb=true';
            $message      = __('Osclass &raquo; Has some errors') . PHP_EOL;
            $message      .= __('We\'ve encountered some problems while updating the database structure. 
            The following queries failed:' . PHP_EOL);
            $message      .= implode(PHP_EOL, $error_queries[2]) . PHP_EOL;
            $message      .= sprintf(
                __('These errors could be false-positive errors. If you\'re sure that is the case, you can 
                    <a href="%s">continue with the upgrade</a>, or <a href="https://osclass.discourse.group">ask in our forums</a>.'),
                $skip_db_link
            );

            return json_encode(['status' => false, 'message' => $message]);
        }

        if (osc_version() < 390) {
            osc_delete_preference('marketAllowExternalSources');
            osc_delete_preference('marketURL');
            osc_delete_preference('marketAPIConnect');
            osc_delete_preference('marketCategories');
            osc_delete_preference('marketDataUpdate');
        }

        Utils::changeOsclassVersionTo(OSCLASS_VERSION);

        return json_encode(['status' => true, 'message' => __('Osclass DB Upgraded Successfully')]);
    }


    /**
     * Extra actions after upgradeProcess is done
     * @return bool
     */
    public function afterProcessUpgrade()
    {
        return osc_set_preference('update_core_available');
    }

    /**
     * prepare osclass upgrade package info
     *                           [
     *                           's_title' => package title,
     *                           's_source_url' => package source file,
     *                           's_new_version' => package new version, "PHP-standardized" version number string
     *                           's_installed_version' => package installed version, "PHP-standardized" version number
     *                           strings
     *                           's_short_name' => package short_name,
     *                           's_target_directory => installation target directory
     *                           'a_filtered_files => array of directory/files name which shouldn't overwrite
     *                           's_compatible' => csv of compatible osclass version (optional)
     *                           's_prerelease' => true or false (Optional)
     *                           ]
     */
    public static function getPackageInfo()
    {
        $json_url               = 'https://api.github.com/repos/mindstellar/osclass/releases/latest';
        $osclass_package_info_json = (new FileSystem())->getContents($json_url);
        if ($osclass_package_info_json) {
            $aSelfPackage = json_decode($osclass_package_info_json, true);
            if (!$aSelfPackage['draft']) {
                if (isset($aSelfPackage['name'])) {
                    $package_info['s_title'] = $aSelfPackage['name'];
                }
                if (isset($aSelfPackage['assets'][0]['browser_download_url'])) {
                    $download_url        = $aSelfPackage['assets'][0]['browser_download_url'];
                    $package_info['s_source_url'] = $download_url;
                }
                if (isset($aSelfPackage['tag_name'])) {
                    $package_info['s_new_version'] = ltrim(trim($aSelfPackage['tag_name']), 'v');
                }
                $package_info['s_installed_version'] = OSCLASS_VERSION;
                $package_info['s_short_name'] = 'osclass';

                $package_info['s_target_directory'] = ABS_PATH;

                $package_info['a_filtered_files'] = ['oc-content','config.php'];

                $package_info['s_prerelease'] = $aSelfPackage['prerelease'];

                return Plugins::applyFilter('osclass_upgrade_package', $package_info);
            }
        }

        throw new RuntimeException(__('Unable to get osclass upgrade package info from remote url'));
    }
}
