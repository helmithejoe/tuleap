/**
 * CodeX: Breaking Down the Barriers to Source Code Sharing
 *
 * Copyright (c) Xerox Corporation, CodeX, 2007. All Rights Reserved
 *
 * This file is licensed under the CodeX Component Software License
 *
 * @author Anne Hardyau
 * @author Marc Nazarian
 */

package com.xerox.xrce.codex.jri.model;

import com.xerox.xrce.codex.jri.model.wsproxy.ArtifactReportDesc;

/**
 * CxArtifactReportDesc is the class for artifact report desc. This is a light
 * object of report, without the fields (for performance reasons)
 * 
 */
public class CxArtifactReportDesc extends CxFromServer {

    /**
     * The ArtifactReportDesc Object (generated by WSDL2JAVA)
     */
    private ArtifactReportDesc artifactReportDesc;

    /**
     * Constructor from an ArtifactReportDesc Object
     * 
     * @param server the server the report desc belongs to
     * @param artifactReportDesc the ArtifactReportDesc Object
     */
    public CxArtifactReportDesc(CxServer server,
            ArtifactReportDesc artifactReportDesc) {
        super(server);
        this.artifactReportDesc = artifactReportDesc;
    }

    /**
     * Returns the ID of this report desc
     * 
     * @return the ID of this report desc
     */
    public int getID() {
        return artifactReportDesc.getReport_id();
    }

    /**
     * Returns the name of this report desc
     * 
     * @return the name of this report desc
     */
    public String getName() {
        return artifactReportDesc.getName();
    }

    /**
     * Returns the description of this report desc
     * 
     * @return the description of this report desc
     */
    public String getDescription() {
        return artifactReportDesc.getDescription();
    }

    /**
     * Returns the scope of this report desc
     * 
     * @return the scope of this report desc
     */
    public String getScope() {
        return artifactReportDesc.getScope();
    }

    /**
     * Returns the ID of the user that created this report desc (only valid for
     * Personal)
     * 
     * @return the ID of the user that created this report desc (only valid for
     *         Personal)
     */
    public int getUserID() {
        return artifactReportDesc.getUser_id();
    }

}
