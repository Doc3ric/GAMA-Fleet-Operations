package com.gamadriverapp.sqlite

import android.database.Cursor
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteStatement
import com.facebook.react.bridge.*

class SQLiteModule(reactContext: ReactApplicationContext) : ReactContextBaseJavaModule(reactContext) {

    private val dbHelper: GamaDatabaseHelper = GamaDatabaseHelper(reactContext)

    override fun getName(): String {
        return "SQLiteModule"
    }

    private val database: SQLiteDatabase
        get() = dbHelper.writableDatabase

    private fun bindStatementParams(statement: SQLiteStatement, params: ReadableArray?) {
        if (params == null) return

        for (i in 0 until params.size()) {
            val bindIndex = i + 1
            when (params.getType(i)) {
                ReadableType.Null -> statement.bindNull(bindIndex)
                ReadableType.Boolean -> statement.bindLong(bindIndex, if (params.getBoolean(i)) 1L else 0L)
                ReadableType.Number -> {
                    val doubleVal = params.getDouble(i)
                    if (doubleVal == Math.floor(doubleVal) && !java.lang.Double.isInfinite(doubleVal)) {
                        statement.bindLong(bindIndex, doubleVal.toLong())
                    } else {
                        statement.bindDouble(bindIndex, doubleVal)
                    }
                }
                ReadableType.String -> statement.bindString(bindIndex, params.getString(i))
                ReadableType.Map, ReadableType.Array -> {
                    // Fallback stringification if raw complex object passed
                    statement.bindString(bindIndex, params.getDynamic(i).asString())
                }
            }
        }
    }

    @ReactMethod
    fun execute(sql: String, params: ReadableArray?, promise: Promise) {
        try {
            val db = database
            val trimmedSql = sql.trim().uppercase()
            val statement = db.compileStatement(sql)
            try {
                bindStatementParams(statement, params)
                val result = Arguments.createMap()

                if (trimmedSql.startsWith("INSERT")) {
                    val insertId = statement.executeInsert()
                    result.putDouble("insertId", insertId.toDouble())
                    result.putInt("rowsAffected", if (insertId > -1L) 1 else 0)
                } else if (trimmedSql.startsWith("UPDATE") || trimmedSql.startsWith("DELETE")) {
                    val rowsAffected = statement.executeUpdateDelete()
                    result.putInt("rowsAffected", rowsAffected)
                } else {
                    statement.execute()
                    result.putInt("rowsAffected", 0)
                }

                promise.resolve(result)
            } finally {
                statement.close()
            }
        } catch (e: Exception) {
            promise.reject("SQL_EXECUTE_ERROR", e.message, e)
        }
    }

    @ReactMethod
    fun query(sql: String, params: ReadableArray?, promise: Promise) {
        var cursor: Cursor? = null
        try {
            val db = database
            val args: Array<String?>? = if (params != null && params.size() > 0) {
                Array(params.size()) { i ->
                    when (params.getType(i)) {
                        ReadableType.Null -> null
                        ReadableType.Boolean -> if (params.getBoolean(i)) "1" else "0"
                        ReadableType.Number -> {
                            val d = params.getDouble(i)
                            if (d == Math.floor(d) && !java.lang.Double.isInfinite(d)) {
                                d.toLong().toString()
                            } else {
                                d.toString()
                            }
                        }
                        ReadableType.String -> params.getString(i)
                        else -> params.getDynamic(i).asString()
                    }
                }
            } else {
                null
            }

            cursor = db.rawQuery(sql, args)
            val rows = Arguments.createArray()
            val columnNames = cursor.columnNames

            while (cursor.moveToNext()) {
                val row = Arguments.createMap()
                for (i in columnNames.indices) {
                    val colName = columnNames[i]
                    when (cursor.getType(i)) {
                        Cursor.FIELD_TYPE_NULL -> row.putNull(colName)
                        Cursor.FIELD_TYPE_INTEGER -> {
                            val longVal = cursor.getLong(i)
                            row.putDouble(colName, longVal.toDouble())
                        }
                        Cursor.FIELD_TYPE_FLOAT -> row.putDouble(colName, cursor.getDouble(i))
                        Cursor.FIELD_TYPE_STRING -> row.putString(colName, cursor.getString(i))
                        Cursor.FIELD_TYPE_BLOB -> {
                            val blob = cursor.getBlob(i)
                            row.putString(colName, String(blob, Charsets.UTF_8))
                        }
                    }
                }
                rows.pushMap(row)
            }

            promise.resolve(rows)
        } catch (e: Exception) {
            promise.reject("SQL_QUERY_ERROR", e.message, e)
        } finally {
            cursor?.close()
        }
    }

    @ReactMethod
    fun transaction(statements: ReadableArray, promise: Promise) {
        val db = database
        db.beginTransaction()
        try {
            for (i in 0 until statements.size()) {
                val op = statements.getMap(i) ?: continue
                val sql = op.getString("sql") ?: throw IllegalArgumentException("Missing 'sql' in transaction operation at index $i")
                val params = if (op.hasKey("params")) op.getArray("params") else null

                val statement = db.compileStatement(sql)
                try {
                    bindStatementParams(statement, params)
                    val trimmed = sql.trim().uppercase()
                    if (trimmed.startsWith("INSERT")) {
                        statement.executeInsert()
                    } else if (trimmed.startsWith("UPDATE") || trimmed.startsWith("DELETE")) {
                        statement.executeUpdateDelete()
                    } else {
                        statement.execute()
                    }
                } finally {
                    statement.close()
                }
            }
            db.setTransactionSuccessful()
            promise.resolve(true)
        } catch (e: Exception) {
            promise.reject("SQL_TRANSACTION_ERROR", e.message, e)
        } finally {
            db.endTransaction()
        }
    }

    @ReactMethod
    fun resetDatabase(promise: Promise) {
        try {
            val db = database
            db.execSQL("DELETE FROM sync_queue;")
            db.execSQL("DELETE FROM local_trips;")
            promise.resolve(true)
        } catch (e: Exception) {
            promise.reject("SQL_RESET_ERROR", e.message, e)
        }
    }
}
