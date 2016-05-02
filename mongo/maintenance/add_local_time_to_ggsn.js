function getTimezoneOffsetInSeconds(timezone) {
	var sign = timezone[0] == '+' ? 1 : -1;
	var hours = timezone.substr(1, 2);
	var minutes = timezone.substr(-2, 2);
	return sign * (hours * 3600 + minutes * 60);
}
var d;
db.lines.find({type: "ggsn", unified_record_time: {$gte: ISODate("2015-12-31T23:00:00+02:00")}, callEventStartTimeStamp: {$exists: false}}).forEach(function (obj) {
	if ('ms_timezone' in obj) {
		d = (new Date(obj.urt.getTime() + getTimezoneOffsetInSeconds(obj.ms_timezone) * 1000)).toISOString();
		obj.callEventStartTimeStamp = d.substr(0, 4) + d.substr(5, 2) + d.substr(8, 2) + d.substr(11, 2) + d.substr(14, 2) + d.substr(17, 2);
	} else {
		obj.callEventStartTimeStamp = obj.record_opening_time;
	}
	db.lines.save(obj);
	print(obj.stamp + "\t" + obj.callEventStartTimeStamp);
})
