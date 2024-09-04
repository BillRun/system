from .revision_info_schema import REVISION_INFO_SCHEMA

SERVICES_POST_SCHEMA = {
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
                "name": {
                    "type": "string"
                },
                "price": {
                    "type": "array",
                    "items": [
                        {
                            "type": "object",
                            "properties": {
                                "price": {
                                    "type": "integer"
                                },
                                "from": {
                                    "type": "integer"
                                },
                                "to": {
                                    "type": "string"
                                }
                            },
                            "required": [
                                "price",
                                "from",
                                "to"
                            ]
                        }
                    ]
                },
                "description": {
                    "type": "string"
                },
                "prorated": {
                    "type": "boolean"
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
                "to",
                "name",
                "price",
                "description",
                "prorated",
                "from",
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

SERVICES_GET_SHEMA = {
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
                        "to": {
                            "type": "string"
                        },
                        "name": {
                            "type": "string"
                        },
                        "price": {
                            "type": "array",
                            "items": [
                                {
                                    "type": "object",
                                    "properties": {
                                        "price": {
                                            "type": "integer"
                                        },
                                        "from": {
                                            "type": "integer"
                                        },
                                        "to": {
                                            "type": "string"
                                        }
                                    },
                                    "required": [
                                        "price",
                                        "from",
                                        "to"
                                    ]
                                }
                            ]
                        },
                        "description": {
                            "type": "string"
                        },
                        "prorated": {
                            "type": "boolean"
                        },
                        "from": {
                            "type": "string"
                        },
                        "creation_time": {
                            "type": "string"
                        },
                        "revision_info": REVISION_INFO_SCHEMA
                    },
                    "required": [
                        "_id",
                        "to",
                        "name",
                        "price",
                        "description",
                        "prorated",
                        "from",
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
