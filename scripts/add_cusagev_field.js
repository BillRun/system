var valid_archive_lines = db.archive.find();
var false_field = [false, null];
valid_archive_lines.forEach(line => {
    var cusagev = line.usagev;
    if (line.is_split_row === true && false_field.includes(line.split_during_mediation)) {
        cusagev = 0;
    }
    line.cusagev = cusagev;
    db.archive.save(line);
    db.lines.update({stamp: line.u_s},{$inc: {'cf.cusagev': line.cf.cusagev}});
});