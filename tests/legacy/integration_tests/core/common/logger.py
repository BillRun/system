import logging

LOGGER = logging.getLogger(__name__)
LOGGER.setLevel(logging.INFO)

FORMATTER = logging.Formatter('%(asctime)s %(name)-5s %(levelname)-8s %(message)s')

CONSOLE = logging.StreamHandler()
CONSOLE.setLevel(logging.INFO)
CONSOLE.setFormatter(FORMATTER)

LOGGER.addHandler(CONSOLE)
