<?php
/**
 * Copyright (c) STMicroelectronics, 2010. All Rights Reserved.
 *
 * 
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

require_once('src/www/include/pre.php');    
require_once('src/common/include/HTTPRequest.class.php');
require_once('/prj/codex/terzino/servers/sources/Webdav_proj/plugins/docman/include/Docman_Error_PermissionDenied.class.php');


$request = HTTPRequest::instance();
$func = $request->getValidated('func', new Valid_WhiteList('docman_access_request'));

if ($request->isPost() && $request->exist('Submit') &&  $request->existAndNonEmpty('func') && $func == 'docman_access_request') {
        $sendMail = new Docman_Error_PermissionDenied();
        $messageToAdmin = $request->get('msg_docman_access');
        $sendMail->processMail($messageToAdmin);
        exit;
}


$HTML->header(array('title'=>$Language->getText('sendmessage', 'title',array($to_msg))));

?>