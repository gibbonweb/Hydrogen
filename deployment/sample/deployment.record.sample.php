<?php
/**
 * This file shows how the app's deployment history is structured.
 * This template should be copied and renamed to a suitable location
 * (e.g. myapp/deployment/deployment.history.php) and hydrogen.autoconfig.php
 * has to be adapted to point to this file.
 * 
 * The history is structured as follows:
 * Legend:
 *  -   <DID>   stands for the deployment ID - this must be a unique integer
 *  -   <DTYPE> specifies the deployment type. Possible values are:
 *                  - "database" for database queries
 *                      -   for now, the corresponding <DACTION> value must be
 *                          a valid SQL statement in string form. Once the Query
 *                          class supports INSERT queries, this will preferrably
 *                          accept Query objects.
 *                  - "system" for system calls
 *                      -   the corresponding <DACTION> is the actual command
 *                          which should be executed on the system, e.g.
 *                          "mkdir uploads".
 *                          *THIS MAY CHANGE* as the current solution is not
 *                          platform independent.
 * Structure:
 *      $deploymentHistory[<DID>] = array(  "type"  => <DTYPE>,
 *                                          "action"=> <DACTION>);
 */