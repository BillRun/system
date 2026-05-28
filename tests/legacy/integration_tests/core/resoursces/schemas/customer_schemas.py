from .revision_info_schema import REVISION_INFO_SCHEMA

CUSTOMER_POST_SCHEMA = {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "type": "object",
    "properties": {
        "status": {
            "type": "integer"
        },
        "details": {
            "type": "boolean"
        },
        "entity": {
            "type": "object",
            "properties": {
                "_id": {
                    "type": "object",
                    "properties": {
                        "$id": {
                            "type": "string"
                        }
                    },
                    "required": [
                        "$id"
                    ]
                },
                "aid": {
                    "type": "integer"
                },
                "firstname": {
                    "type": "string"
                },
                "lastname": {
                    "type": "string"
                },
                "email": {
                    "type": "string"
                },
                "address": {
                    "type": "string"
                },
                "type": {
                    "type": "string"
                },
                "creation_time": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "from": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "to": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                }
            },
            "required": [
                "_id",
                "aid",
                "firstname",
                "lastname",
                "email",
                "address",
                "type",
                "creation_time",
                "from",
                "to"
            ]
        }
    },
    "required": [
        "status",
        "details",
        "entity"
    ]
}

CUSTOMER_GET_SCHEMA = {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "type": "object",
    "properties": {
        "status": {
            "type": "integer"
        },
        "next_page": {
            "type": "boolean"
        },
        "details": {
            "type": "array",
            "items": [
                {
                    "type": "object",
                    "properties": {
                        "_id": {
                            "type": "object",
                            "properties": {
                                "$id": {
                                    "type": "string"
                                }
                            },
                            "required": [
                                "$id"
                            ]
                        },
                        "aid": {
                            "type": "integer"
                        },
                        "firstname": {
                            "type": "string"
                        },
                        "lastname": {
                            "type": "string"
                        },
                        "email": {
                            "type": "string"
                        },
                        "address": {
                            "type": "string"
                        },
                        "type": {
                            "type": "string"
                        },
                        "creation_time": {
                            "type": "string",
                        },
                        "from": {
                            "type": "string"
                        },
                        "to": {
                            "type": "string"
                        },
                        "revision_info": REVISION_INFO_SCHEMA
                    },
                    "required": [
                        "_id",
                        "aid",
                        "firstname",
                        "lastname",
                        "email",
                        "address",
                        "type",
                        "creation_time",
                        "from",
                        "to",
                        "revision_info"
                    ]
                }
            ]
        }
    },
    "required": [
        "status",
        "next_page",
        "details"
    ]
}
