package com.gamadriverapp.sqlite

import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class GamaDatabaseHelper(context: Context) : SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION) {

    companion object {
        const val DATABASE_NAME = "gama_driver.db"
        const val DATABASE_VERSION = 1

        private const val SQL_CREATE_LOCAL_TRIPS = """
            CREATE TABLE IF NOT EXISTS local_trips (
                client_id TEXT PRIMARY KEY,
                server_id INTEGER,
                driver_id INTEGER NOT NULL,
                vehicle_id INTEGER NOT NULL,
                driver_vehicle_assignment_id INTEGER,
                trip_date TEXT NOT NULL,
                time_in TEXT NOT NULL,
                time_out TEXT,
                origin_latitude REAL NOT NULL,
                origin_longitude REAL NOT NULL,
                origin_accuracy REAL,
                origin_address TEXT,
                destination_latitude REAL,
                destination_longitude REAL,
                destination_accuracy REAL,
                destination_address TEXT,
                status TEXT NOT NULL,
                sync_status TEXT NOT NULL,
                sync_error TEXT,
                remarks TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
        """

        private const val SQL_CREATE_SYNC_QUEUE = """
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id TEXT NOT NULL,
                action TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                status TEXT NOT NULL,
                retry_count INTEGER DEFAULT 0,
                last_attempt_at TEXT,
                error_message TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (client_id) REFERENCES local_trips(client_id) ON DELETE CASCADE
            );
        """
    }

    override fun onConfigure(db: SQLiteDatabase) {
        super.onConfigure(db)
        db.setForeignKeyConstraintsEnabled(true)
        db.enableWriteAheadLogging()
    }

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(SQL_CREATE_LOCAL_TRIPS)
        db.execSQL(SQL_CREATE_SYNC_QUEUE)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_local_trips_driver_status ON local_trips(driver_id, status);")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_local_trips_sync_status ON local_trips(sync_status);")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_sync_queue_status_id ON sync_queue(status, id);")
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        // Migration logic for future schema versions
    }
}
