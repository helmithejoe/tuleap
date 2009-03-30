<?php
//
// Copyright (c) Xerox Corporation, CodeX Team, 2001-2003. All rights reserved
//
// 
//
//
//  Written for CodeX by Stephane Bouhet
//

require_once('common/include/Error.class.php');
//require_once('common/tracker/ArtifactType.class.php');

class GroupFactory extends Error {

	/**
	 *  Constructor.
	 *
	 *	@return	boolean	success.
	 */
	function GroupFactory() {
            if (isset($GLOBALS['Language']))
            $this->Error();
            return true;
	}

	/**
	 *	return a resultset of Group
	 *
	 *	@return	resultset
	 */
	function getAllGroups() {
	        global $Language;
		if (user_isloggedin()) {
			// For  surperuser), we can see all the trackers (both public and non public)
                    if ( user_is_super_user() ) {
				$public_flag='0,1';
			} else {
				$public_flag='1';
			}
		} else {
                    if (isset($GLOBALS['Language']))
			$this->setError($Language->getText('include_exit','perm_denied'));
                    return false;
		}

		$sql="SELECT group_id,group_name,unix_group_name FROM groups
			WHERE group_id <> 100 AND status = 'A'
			AND is_public IN ($public_flag)
			ORDER BY group_name ASC";

		//echo $sql;
		
		$result = db_query ($sql);

		$rows = db_numrows($result);

		if (!$result || $rows < 1) {
                    if (isset($GLOBALS['Language']))
                        $this->setError($Language->getText('include_common_groupfactory','none_found',db_error()));
                    return false;
		}
		return $result;
	}

	/**
	 *	return a resultset of Group for the current user
	 *
	 *	@return	resultset
	 */
	function getMemberGroups() {
                global $Language;
		if (!user_isloggedin()) {
			$this->setError($Language->getText('include_exit','perm_denied'));
			return false;
		}

		$sql="SELECT g.group_id,g.group_name ".
			 "FROM groups g, user_group ug ".
			 "WHERE g.group_id <> 100 AND g.status = 'A' AND g.group_id = ug.group_id ".
			 "AND ug.user_id=".user_getid()." ".
			 "ORDER BY g.group_name ASC";

		//echo $sql;
		
		$result = db_query ($sql);

		$rows = db_numrows($result);

		if (!$result || $rows < 1) {
			$this->setError($Language->getText('include_common_groupfactory','none_found',db_error()));
			return false;
		}
		return $result;
	}

    /**
	 *	return an array of Group for the current user (the groups the user is member of)
	 *
	 *	@return	array of {Group}
	 */
	function getMyGroups() {
        global $Language;
		$result_my_groups = $this->getMemberGroups();
        if ($this->isError() || !$result_my_groups) {
            return false;
        } else {
            $pm = ProjectManager::instance();
            $my_groups = array();
            while ($res_group = db_fetch_array($result_my_groups)) {
                $group = $pm->getProject($res_group['group_id']);
                if ($group && !$group->isError()) {
                    $my_groups[$group->getID()] = $group;
                }
            }
            return $my_groups;
        }
	}

}

?>
