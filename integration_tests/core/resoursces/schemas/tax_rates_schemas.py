from .revision_info_schema import REVISION_INFO_SCHEMA

TAX_RATES_POST_SCHEMA = {
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
                "key": {
                    "type": "string"
                },
                "description": {
                    "type": "string"
                },
                "rate": {
                    "type": "number"
                },
                "embed_tax": {
                    "type": "boolean"
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
                }
            },
            "required": [
                "_id",
                "from",
                "key",
                "description",
                "rate",
                "embed_tax",
                "to",
                "creation_time"
            ]
        }
    },
    "required": [
        "status",
        "details",
        "entity"
    ]
}

TAX_RATES_GET_SCHEMA = {
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
                        "from": {
                            "type": "string"
                        },
                        "to": {
                            "type": "string"
                        },
                        "key": {
                            "type": "string"
                        },
                        "description": {
                            "type": "string"
                        },
                        "rate": {
                            "type": "number"
                        },
                        "embed_tax": {
                            "type": "boolean"
                        },
                        "creation_time": {
                            "type": "string"
                        },
                        "revision_info": REVISION_INFO_SCHEMA
                    },
                    "required": [
                        "_id",
                        "from",
                        "to",
                        "key",
                        "description",
                        "rate",
                        "embed_tax",
                        "creation_time",
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
