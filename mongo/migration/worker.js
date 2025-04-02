/* 
 * worker migration script.
 */

// =============================== Helper functions ============================

// Perform specific migrations only once
// Important note: runOnce is guaranteed to run some migration code once per task code only if the whole migration script completes without errors.
function runOnce(lastConfig, taskCode, callback) {
    if (typeof lastConfig.past_migration_tasks === 'undefined') {
        lastConfig['past_migration_tasks'] = [];
    }
    taskCode = taskCode.toUpperCase();
    if (!lastConfig.past_migration_tasks.includes(taskCode)) {
        if (new RegExp(/.*-\d+$/).test(taskCode)) {
            print("running task " + taskCode);
            callback();
            lastConfig.past_migration_tasks.push(taskCode);
        } else {
            print('Illegal task code ' + taskCode);
        }
    } else {
        //        print('task ' + taskCode + ' already applied in this environment');
    }
    return lastConfig;
}

// =============================================================================
var lastConfig = db.config.find().sort({ _id: -1 }).limit(1).pretty().next();
delete lastConfig['_id'];
// =============================================================================

//BRCD-4422: Add job queue special settings
runOnce(lastConfig, 'BRCD-4422-1', function () {
	lastConfig.worker = {
		"enabled": true, // this will move front-end features to work with queue instead of the default
		"iteration": 250000, // time the iterate between jobs fetching in ms
		"job_timeout":3600, // job timeout
		"concurrent_limit": 10, // how many jobs run in parallel in single instance
//		cron: {
//			"enabled": true, //if you want to run cron through standard linux cron
//			"timeout": 55, // stop the cron after 55 seconds
//		}
	}
});


db.config.insertOne(lastConfig);
