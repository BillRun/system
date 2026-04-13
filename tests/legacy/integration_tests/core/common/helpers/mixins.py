class GetAttributes:
    @classmethod
    def _all_not_callable_attrs(cls):
        return {
            attr: value
            for attr, value in cls.__dict__.items()
            if not attr.startswith("__") and not callable(getattr(cls, attr))
        }

    @classmethod
    def as_list(cls):
        return list(cls._all_not_callable_attrs().values())

    @classmethod
    def as_dict(cls):
        return cls._all_not_callable_attrs()

