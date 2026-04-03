from __future__ import annotations

from contextlib import contextmanager

import pymysql

from .config import settings


def connect():
    return pymysql.connect(
        host=settings.db_host,
        port=settings.db_port,
        user=settings.db_user,
        password=settings.db_pass,
        database=settings.db_name,
        charset=settings.db_charset,
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


@contextmanager
def db_cursor():
    connection = connect()
    try:
        with connection.cursor() as cursor:
            yield connection, cursor
        connection.commit()
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()

