// External variables :
//		trgtColl : the collection to remove data from
//		daysToRmove : how much days to remove   before the script ends.
//		monthsToKeep : The minimum amount of data  to keep in the DB in months
if (trgtColl && daysToRmove && monthsToKeep) {
	var firstLine = db.getCollection(trgtColl).find({urt:{$exists:1}}).sort({urt:1}).limit(1).next();
	if(!firstLine) {
		print("No data left in collection "+ trgtColl +" !!");
		exit();
	}
	var firstLineDate = firstLine.urt.getTime(); //first cdr time  in millisecs
	var millisecToAdvance = 60000; //how  much to advance one each meta step
	var i = (daysToRmove * 86400 * 1000); //millisecs to  remove X (30) days after first CDR
	var targetDate = firstLineDate + i; //The date to  remove  up to in milli
	var upperLimitDateMili = ISODate().getTime() - (monthsToKeep * 31 * 86400 * 1000);
	var chunkSize = 500; // how uch lines  to remove  each time
	var interval = 150; //how much  to wait  between removals
	var totalRemoved = 0; //count  of the  total lines  removed

	print("Upper date limit for removal on " + trgtColl + " collection is: " + new Date(upperLimitDateMili) );
	for(; i >= 0  && (targetDate-i) < upperLimitDateMili; i-=millisecToAdvance) {
		//find  the documents  the  should be removed in this step
		let horizon = targetDate-i;
		let sectionEndCursor = db.getCollection(trgtColl).find({urt:{$lt:new Date(horizon)}}).sort({urt:-1}).limit(1);
		print("Horizon  set to :" + new Date(horizon));
		if(!sectionEndCursor.hasNext()) {
			continue;
		}
		var sectionEnd = sectionEndCursor.next();
		var cursor = db.getCollection(trgtColl).find({urt:{$lt:new Date(horizon),$lte:sectionEnd.urt}}).sort({urt:1}).limit(1);
		//Remove documents in chunks of size chunkSize
		while(cursor.hasNext()) {
			var end = cursor.next();
			var linesCount = db.getCollection(trgtColl).count({urt:{$lt:new Date(horizon)},_id:{$lte:end._id}});
			print("Removing :" + linesCount + " lines before the date of : " +  new Date(horizon)+ " with id smaller then :" + end._id);
			//Actually remove!
			var removedResults = db.getCollection(trgtColl).remove({urt:{$lt:new Date(horizon)},_id:{$lte:end._id}});
			//acummulate removal count
			if(removedResults.nRemoved) {
				totalRemoved += removedResults.nRemoved;
			} else if(removedResults.deletedCount) {
				totalRemoved += removedResults.deletedCount;
			}

			//wait and advance...
			sleep(interval);
			cursor = db.getCollection(trgtColl).find({urt:{$lt:new Date(horizon)},_id:{$lte:sectionEnd._id}}).sort({_id:1}).skip(chunkSize).limit(1);
		}
		print( "Removed " + totalRemoved + " lines.");
	}
	print("------------------------------------------------------------");
	print( "Total CDRs removed :  " + totalRemoved + " from: "+trgtColl);
} else {
	print("Missing configuration not removing old data!");
}
