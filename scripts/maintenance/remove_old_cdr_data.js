// var trgtColl="lines";
// var daysToRmove = 30; // The amount of days to remove in each script  execution.
// var monthsToKeep = 6; // The amount  of  day  in month to keep in the DB.
if (trgtColl && daysToRmove && monthsToKeep) {
	var firstLineDate = db.getCollection(trgtColl).find({urt:{$exists:1}}).sort({urt:1}).limit(1).next().urt.getTime(); //first cdr time  in millisecs
	var millisecToAdvance = 60000; //how  much to advance one each meta step
	var i = (daysToRmove * 86400 * 1000); //millisecs to  remove X (30) days after first CDR
	var targetDate = firstLineDate + i; //The date to  remove  up to in milli
	var upperLimitDateMili = ISODate().getTime() - (monthsToKeep * 31 * 86400 * 1000);
	var chunkSize = 500; // how uch lines  to remove  each time
	var interval = 150; //how much  to wait  between removals
	var totalRemoved = 0; //count  of the  total lines  removed

	for(; i >= 0  && (targetDate-i) < upperLimitDateMili; i-=millisecToAdvance) {
		let horizon = targetDate-i;
		let sectionEndCursor = db.getCollection(trgtColl).find({urt:{$lt:new Date(horizon)}}).sort({urt:-1}).limit(1);
		print("Horizon  set to :" + new Date(horizon));
		if(!sectionEndCursor.hasNext()) {
			continue;
		}
		var sectionEnd = sectionEndCursor.next();
		var cursor = db.getCollection(trgtColl).find({urt:{$lt:new Date(horizon),$lte:sectionEnd.urt}}).sort({urt:1}).limit(1);
		while(cursor.hasNext()) {
			var end = cursor.next();
			var linesCount = db.getCollection(trgtColl).count({urt:{$lt:new Date(horizon)},_id:{$lte:end._id}});
			print("Removing :" + linesCount + " lines before the date of : " +  new Date(horizon)+ " with id smaller then :" + end._id);
			var removedResults = db.getCollection(trgtColl).remove({urt:{$lt:new Date(horizon)},_id:{$lte:end._id}});
			if(removedResults.nRemoved) {
				totalRemoved += removedResults.nRemoved;
			}
			printjson(totalRemoved);
			sleep(interval);
			cursor = db.getCollection(trgtColl).find({urt:{$lt:new Date(horizon)},_id:{$lte:sectionEnd._id}}).sort({_id:1}).skip(chunkSize).limit(1);
		}
		print( "Removed " + totalRemoved + " lines.");
	}
} else {
	print("Missing configuration not removing old data!");
}
