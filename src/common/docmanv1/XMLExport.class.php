<?php
/**
 * Copyright (c) Enalean, 2014. All Rights Reserved.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

class DocmanV1_XMLExport {
    private $archive_name;
    private $project;
    private $data_path;
    private $package_path;

    public function __construct(Project $project, $archive_name) {
        $this->project      = $project;
        $this->archive_name = basename($archive_name);
        $this->package_path = $archive_name;
        $this->data_path    = $this->package_path.'/'.$this->archive_name;
    }

    public function createDomDocument() {
        $implementation    = new DOMImplementation();
        $dtd               = $implementation->createDocumentType('docman', '', get_server_url().'/plugins/docman/docman-1.0.dtd');
        $doc               = $implementation->createDocument('', '', $dtd);
        $doc->encoding     = 'UTF-8';
        $doc->standalone   = 'no';
        $doc->version      = '1.0';
        $doc->formatOutput = true;
        return $doc;
    }

    public function dumpPackage() {
        $this->createDirectories();
        $doc = $this->dump();
        $doc->save($this->package_path.'/'.$this->archive_name.'.xml');
    }

    public function dump() {
        $doc = $this->createDomDocument();
        $this->appendDocman($doc);
        $doc->validate();
        return $doc;
    }

    public function appendDocman(DOMDocument $doc) {
        $export = new DocmanV1_XMLExportData(
            new DocmanV1_XMLExportDao(),
            UserManager::instance(),
            new UGroupManager(),
            $doc,
            $this->data_path
        );

        $docman = $doc->createElement('docman');
        $doc->appendChild($docman);

        $ugroups = $doc->createElement('ugroups');
        $docman->appendChild($ugroups);

        $docman->appendChild($export->getTree($this->project, $ugroups));
        $export->appendUGroups($ugroups, $this->project);
    }

    public function createDirectories() {
        mkdir($this->data_path, 0755, true);
    }
}