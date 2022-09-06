REVISION_INFO_SCHEMA = {
    "type": "object",
    "properties": {
        "status": {
            "type": "string"
        },
        "is_last": {
            "type": "boolean"
        },
        "early_expiration": {
            "type": "boolean"
        },
        "updatable": {
            "type": "boolean"
        },
        "closeandnewable": {
            "type": "boolean"
        },
        "movable": {
            "type": "boolean"
        },
        "removable": {
            "type": "boolean"
        },
        "movable_from": {
            "type": "boolean"
        },
        "movable_to": {
            "type": "boolean"
        }
    }
}
