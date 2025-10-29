import os
import re
import pandas as pd
import numpy as np
from sqlalchemy import create_engine, text
from dateutil.relativedelta import relativedelta
from datetime import datetime

# --- KONFIGURASI PATH ---
BASE_PATH = 'C:/Users/USER/Documents/Arsip-Magang/BMKG/web-chat/web-informasi-iklim/data'
PATH_NORMAL = os.path.join(BASE_PATH, 'data-rata-rata', 'Grid_Desa_20251021_Table.xls')
PATH_ANALISIS = os.path.join(BASE_PATH, 'data-analisis')
PATH_PREDIKSI = os.path.join(BASE_PATH, 'data-prediksi')
PATH_DAS_PREDIKSI = os.path.join(BASE_PATH, 'data-prediksi-das')

# --- KONFIGURASI DATABASE ---
DB_HOST = '127.0.0.1'
DB_DATABASE = 'visualdatabmkg_test'
DB_USERNAME = 'root'
DB_PASSWORD = ''

# Buat koneksi engine sekali saja
engine = create_engine(f'mysql+pymysql://{DB_USERNAME}:{DB_PASSWORD}@{DB_HOST}/{DB_DATABASE}')

# --- Fungsi Generik untuk Impor, Tambah Kolom, Update, dan Index ---
def import_table_and_create_spatial(table_name, df_insert, is_full_replace=True, periods_to_delete=None):
    """
    Fungsi untuk insert data, tambah/isi kolom spasial, dan index.
    :param table_name: Nama tabel target.
    :param df_insert: DataFrame yang sudah bersih untuk dimasukkan.
    :param is_full_replace: True jika TRUNCATE, False jika DELETE by period.
    :param periods_to_delete: List periode string (YYYY-MM) jika is_full_replace=False.
    """
    with engine.connect() as connection:
        with connection.begin() as transaction:
            try:
                # 1. Hapus data lama (TRUNCATE atau DELETE)
                if is_full_replace:
                    print(f"  - Menghapus data lama ({table_name}: TRUNCATE)...")
                    connection.execute(text(f"TRUNCATE TABLE `{table_name}`"))
                    # Hapus kolom location lama jika ada (agar ADD COLUMN tidak error)
                    print(f"  - Menghapus kolom 'location' lama jika ada ({table_name})...")
                    connection.execute(text(f"ALTER TABLE `{table_name}` DROP COLUMN IF EXISTS `location`"))
                elif periods_to_delete:
                    print(f"  - Menghapus data lama ({table_name}: {len(periods_to_delete)} periode)...")
                    delete_query = text(f"DELETE FROM `{table_name}` WHERE data_period IN :periods")
                    connection.execute(delete_query, {'periods': periods_to_delete})
                    # Untuk analisis, kita asumsikan kolom location akan dibuat ulang jika belum ada
                    connection.execute(text(f"ALTER TABLE `{table_name}` DROP COLUMN IF EXISTS `location`"))
                else:
                    raise ValueError("Harus TRUNCATE atau menyediakan periods_to_delete.")

                # 2. Masukkan data baru (tanpa kolom location)
                print(f"  - Memasukkan {len(df_insert)} baris data baru ({table_name}, chunksize=100)...")
                df_insert.to_sql(table_name, con=connection, if_exists='append', index=False, chunksize=100)

                # --- 3. Buat dan Isi Kolom Spasial ---
                print(f"  - Menambahkan kolom 'location' ({table_name})...")
                # Tambah kolom HANYA jika belum ada (antisipasi jika rollback gagal total)
                connection.execute(text(f"ALTER TABLE `{table_name}` ADD COLUMN IF NOT EXISTS `location` POINT NULL"))

                print(f"  - Mengisi kolom 'location' ({table_name})...")
                where_clause_update = ""
                if not is_full_replace and periods_to_delete:
                    periods_str = "','".join(periods_to_delete)
                    where_clause_update = f"AND data_period IN ('{periods_str}')" # Update hanya periode baru

                update_sql = f"""
                    UPDATE `{table_name}`
                    SET location = ST_PointFromText(CONCAT('POINT(', longitude, ' ', latitude, ')'))
                    WHERE longitude IS NOT NULL AND latitude IS NOT NULL
                    AND TRIM(IFNULL(longitude,'')) != '' AND TRIM(IFNULL(latitude,'')) != ''
                    {where_clause_update}
                """
                result = connection.execute(text(update_sql.strip()))
                print(f"    -> {result.rowcount} baris diperbarui.")

                # 4. Ubah kolom menjadi NOT NULL (hanya jika ada data yang diupdate)
                #    Ini mencegah error jika tabel jadi kosong total
                #    Kita juga perlu handle jika kolom sudah NOT NULL dari run sebelumnya
                try:
                    print(f"  - Mengubah 'location' menjadi NOT NULL ({table_name})...")
                    connection.execute(text(f"ALTER TABLE `{table_name}` MODIFY COLUMN `location` POINT NOT NULL"))
                except Exception as modify_err:
                    # Abaikan error jika kolom sudah NOT NULL atau jika tabel kosong
                    if "Invalid use of NULL value" in str(modify_err) or "Duplicate column name" in str(modify_err):
                        print(f"    -> Peringatan saat MODIFY: {modify_err}. Kemungkinan kolom sudah NOT NULL atau ada NULL tersisa.")
                    else:
                        raise modify_err # Tampilkan error lain


                # 5. Tambahkan SPATIAL INDEX
                print(f"  - Menambahkan SPATIAL INDEX pada 'location' ({table_name})...")
                index_name = f"{table_name}_location_spatialindex"
                # Hapus index lama jika ada
                connection.execute(text(f"DROP INDEX IF EXISTS `{index_name}` ON `{table_name}`"))
                connection.execute(text(f"ALTER TABLE `{table_name}` ADD SPATIAL INDEX `{index_name}`(`location`)"))

                transaction.commit()
                print(f"Impor & Pembuatan Spasial ({table_name}) BERHASIL.")

            except Exception as e:
                print(f"  - GAGAL ({table_name}): Transaksi dibatalkan. {e}")
                transaction.rollback()
                raise

def import_normals():
    print("\n--- Memulai Impor Data Normal ---")
    try:
        df = pd.read_excel(PATH_NORMAL)
        df.rename(columns={
            'URUT': 'urut', 'NO_GRID': 'no_grid', 'LAT': 'latitude', 'LON': 'longitude',
            'ZOM9120__1': 'zom_1','ZOM9120_NA': 'zom_na', 'TIPE_ZOM': 'tipe_zom', 'CHTHN': 'chthn',
            'ID_PROV38': 'province', 'ID_KABKOTA_IKN': 'regency', 'Kecamatan': 'kecamatan', 'Kelurahan/Desa': 'desa',
            'JAN': 'jan', 'FEB': 'feb', 'MAR': 'mar', 'APR': 'apr', 'MAY': 'may', 'JUN': 'jun',
            'JUL': 'jul', 'AUG': 'aug', 'SEP': 'sep', 'OCT': 'oct', 'NOV': 'nov', 'DEC': 'dec'
        }, inplace=True)

        cols_to_round = [
            'latitude', 'longitude', 'chthn', 'jan', 'feb', 'mar', 'apr', 'may',
            'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'
        ]
        all_numeric_cols = cols_to_round + ['ZOM9120_NA', 'urut', 'nogrid']

        for col in all_numeric_cols:
            if col in df.columns:
                df[col] = pd.to_numeric(df[col], errors='coerce')

        original_count = len(df)
        df.dropna(subset=['latitude', 'longitude'], inplace=True)
        dropped_count = original_count - len(df)
        if dropped_count > 0:
            print(f"  INFO: Dihapus {dropped_count} baris karena lat/lon kosong/tidak valid.")

        df[cols_to_round] = df[cols_to_round].round(2)

        db_columns = ['urut', 'nogrid', 'longitude', 'latitude', 'zom_1', 'ZOM9120_NA', 'tipe_zom', 'chthn', 'province', 'regency', 'kecamatan', 'desa', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
        kolom_insert = [col for col in df.columns if col in db_columns]
        df_insert = df[kolom_insert].copy()

        if 'latitude' in df_insert.columns:
            df_insert['latitude'] = df_insert['latitude'].astype(float)
        if 'longitude' in df_insert.columns:
            df_insert['longitude'] = df_insert['longitude'].astype(float)

        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)

        # Panggil fungsi generik dengan mode replace=True
        import_table_and_create_spatial('climate_normals', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Data Normal: {e}")

def import_analyses():
    print("\n--- Memulai Impor Data Analisis ---")
    try:
        today = datetime.now()
        target_periods_ym = []
        for i in range(1, 4):
            target_date = today - relativedelta(months=i)
            target_periods_ym.append(target_date.strftime('%Y%m'))
        target_periods_ym.sort()
        print(f"INFO: Mencari file analisis untuk periode target: {target_periods_ym}")

        all_files_in_dir = os.listdir(PATH_ANALISIS)
        files_to_process = []
        for file_name in all_files_in_dir:
            match = re.search(r'(\d{6})', file_name)
            if match and match.group(1) in target_periods_ym:
                files_to_process.append(file_name)

        if not files_to_process:
            print("SELESAI: Tidak ada file analisis yang cocok.")
            return
        print(f"File yang akan diproses: {sorted(files_to_process)}")

        all_data = []
        periods_to_update_db = []
        for file_name in sorted(files_to_process):
            match = re.search(r'(\d{6})', file_name)
            if not match: continue
            date_str = match.group(1)
            period = f"{date_str[:4]}-{date_str[4:]}"
            periods_to_update_db.append(period)

            file_path = os.path.join(PATH_ANALISIS, file_name)
            df = pd.read_excel(file_path, engine='xlrd')
            df.rename(columns={'LON': 'longitude', 'LAT': 'latitude', 'CH': 'ch'}, inplace=True)

            cols_to_numeric = ['latitude', 'longitude', 'ch']
            for col in cols_to_numeric:
                df[col] = pd.to_numeric(df[col], errors='coerce')

            original_count = len(df)
            df.dropna(subset=['latitude', 'longitude'], inplace=True)
            dropped_count = original_count - len(df)
            if dropped_count > 0:
                print(f"  INFO ({file_name}): Dihapus {dropped_count} baris lat/lon tidak valid.")

            df[cols_to_numeric] = df[cols_to_numeric].round(2)

            df['data_period'] = period
            all_data.append(df[['latitude', 'longitude', 'ch', 'data_period']])

        if not all_data:
            print("SELESAI: Tidak ada data valid dalam file.")
            return
        final_df = pd.concat(all_data, ignore_index=True)

        db_columns = ['latitude', 'longitude', 'ch', 'data_period']
        kolom_insert = [col for col in final_df.columns if col in db_columns]
        df_insert = final_df[kolom_insert].copy()

        if 'latitude' in df_insert.columns:
            df_insert['latitude'] = df_insert['latitude'].astype(float)
        if 'longitude' in df_insert.columns:
            df_insert['longitude'] = df_insert['longitude'].astype(float)

        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)
        unique_periods = list(set(periods_to_update_db))

        # Panggil fungsi generik dengan mode replace=False dan periods_to_delete
        import_table_and_create_spatial('climate_analyses', df_insert, is_full_replace=False, periods_to_delete=unique_periods)

    except Exception as e:
        print(f"GAGAL Impor Analisis: {e}")


def import_predictions():
    print("\n--- Memulai Impor Data Prediksi ---")
    try:
        current_month = datetime.now().month
        current_year = datetime.now().year
        print(f"INFO: Mencari file versi {current_year}-{current_month}.")

        files = [f for f in os.listdir(PATH_PREDIKSI) if f.startswith('pch_ensMean') and f.endswith('.csv')]
        if not files:
            print("INFO: Tidak ada file prediksi.")
            return

        all_data = []
        for file_name in files:
            match = re.search(r'(\d{4})\.(\d{2})_ver_(\d{4})\.(\d{2})\.(\d{2})', file_name)
            if not match: continue

            file_version_month = int(match.group(4))
            file_version_year = int(match.group(3))
            if file_version_month != current_month or file_version_year != current_year:
                continue

            print(f"PROSES: Mempersiapkan file '{file_name}'...")
            period = f"{match.group(1)}-{match.group(2)}"
            version = f"{match.group(3)}-{match.group(4)}-{match.group(5)}"

            file_path = os.path.join(PATH_PREDIKSI, file_name)
            df = pd.read_csv(file_path, skiprows=1, header=None, names=['NOGRID', 'LON', 'LAT', 'VAL', 'MIN', 'MAX'])
            df.rename(columns={'NOGRID': 'no_grid', 'LON': 'longitude', 'LAT': 'latitude', 'VAL': 'val'}, inplace=True)

            cols_to_round = ['longitude', 'latitude', 'val', 'MIN', 'MAX']
            all_numeric_cols = cols_to_round + ['no_grid']

            for col in all_numeric_cols:
                if col in df.columns:
                    df[col] = pd.to_numeric(df[col], errors='coerce')

            original_count = len(df)
            df.dropna(subset=['latitude', 'longitude'], inplace=True)
            dropped_count = original_count - len(df)
            if dropped_count > 0:
                print(f"  INFO ({file_name}): Dihapus {dropped_count} baris lat/lon tidak valid.")

            df[cols_to_round] = df[cols_to_round].round(2)

            df['prediction_period'] = period
            df['prediction_version'] = version
            all_data.append(df)

        if not all_data:
            print(f"SELESAI: Tidak ada file prediksi valid untuk versi {current_year}-{current_month}.")
            return

        final_df = pd.concat(all_data, ignore_index=True)

        db_columns = ['NOGRID', 'longitude', 'latitude', 'val', 'MIN', 'MAX', 'prediction_period', 'prediction_version']
        kolom_insert = [col for col in final_df.columns if col in db_columns]
        df_insert = final_df[kolom_insert].copy()

        if 'latitude' in df_insert.columns:
            df_insert['latitude'] = df_insert['latitude'].astype(float)
        if 'longitude' in df_insert.columns:
            df_insert['longitude'] = df_insert['longitude'].astype(float)

        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)

        # Panggil fungsi generik dengan mode replace=True
        import_table_and_create_spatial('climate_predictions', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Prediksi: {e}")

def import_das_predictions():
    print("\n--- Memulai Impor Data Prediksi Dasarian ---")
    try:
        # 1. Tentukan tanggal versi target (Senin/Kamis terbaru)
        today = datetime.now()
        days_to_check = [0, 3] # Senin (0), Kamis (3)
        days_since_last_update = float('inf')
        
        # Cari mundur 7 hari untuk menemukan hari update terbaru
        for i in range(7):
            check_date = today - relativedelta(days=i)
            if check_date.weekday() in days_to_check:
                days_since_last_update = i
                break
        
        if days_since_last_update == float('inf'):
            print("INFO: Tidak ada hari Senin/Kamis dalam 7 hari terakhir? Aneh. Melewatkan.")
            return

        target_version_date_obj = today - relativedelta(days=days_since_last_update)
        target_version_str = target_version_date_obj.strftime('%Y.%m.%d') # e.g., '2025.10.23'
        target_version_db = target_version_date_obj.strftime('%Y-%m-%d') # e.g., '2025-10-23'

        print(f"INFO: Mencari file prediksi dasarian versi: _ver_{target_version_str}.csv")

        files = [f for f in os.listdir(PATH_DAS_PREDIKSI) if f.startswith('pch_det') and f.endswith(f"_ver_{target_version_str}.csv")]
        
        if not files:
            print(f"SELESAI: Tidak ada file prediksi dasarian untuk versi {target_version_str}.")
            return

        all_data = []
        for file_name in files:
            # 2. Ekstrak metadata dari nama file
            # Format: pch_det.YYYY.MM.das.X_ver_...
            match = re.search(r'pch_det\.(\d{4})\.(\d{2})\.das\.(\d)', file_name)
            if not match:
                print(f" WARNING: Melewatkan file dengan format aneh: {file_name}")
                continue

            year = match.group(1)
            month = match.group(2)
            das = match.group(3)
            
            # Format string dasarian: 2025-10-1, 2025-10-2, dst.
            prediction_das_period = f"{year}-{int(month):02d}-{das}"
            
            print(f" - Memproses: {file_name} (Periode: {prediction_das_period})")

            # 3. Baca CSV
            file_path = os.path.join(PATH_DAS_PREDIKSI, file_name)
            # Nama kolom sesuai gambar Anda
            df = pd.read_csv(file_path, skiprows=1, header=None, names=['NOGRID', 'LON', 'LAT', 'VAL', 'MIN', 'MAX', 'SH', 'CHp'])
            
            # 4. Bersihkan data (sama seperti import_predictions)
            df.rename(columns={'NOGRID':'no_grid', 'LON': 'longitude', 'LAT': 'latitude', 'VAL': 'val'}, inplace=True)
            cols_to_round = ['longitude', 'latitude', 'val', 'MIN', 'MAX', 'SH', 'CHp']
            all_numeric_cols = cols_to_round + ['no_grid']

            for col in all_numeric_cols:
                if col in df.columns:
                    df[col] = pd.to_numeric(df[col], errors='coerce')

            df.dropna(subset=['latitude', 'longitude'], inplace=True)
            df[cols_to_round] = df[cols_to_round].round(2)

            # 5. Tambahkan kolom metadata
            df['prediction_das_period'] = prediction_das_period
            df['prediction_version'] = target_version_db
            all_data.append(df)

        if not all_data:
            print("SELESAI: Tidak ada data valid yang diproses.")
            return

        final_df = pd.concat(all_data, ignore_index=True)

        # 6. Siapkan untuk impor DB
        db_columns = ['NOGRID', 'longitude', 'latitude', 'val', 'MIN', 'MAX', 'SH', 'CHp', 'prediction_das_period', 'prediction_version']
        kolom_insert = [col for col in final_df.columns if col in db_columns]
        df_insert = final_df[kolom_insert].copy()

        df_insert['latitude'] = df_insert['latitude'].astype(float)
        df_insert['longitude'] = df_insert['longitude'].astype(float)
        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)
        
        # 7. Panggil fungsi impor (TRUNCATE + INSERT)
        # Kita TRUNCATE karena ini data prediksi, kita hanya ingin versi terbaru.
        import_table_and_create_spatial('climate_das_predictions', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Prediksi Dasarian: {e}")

if __name__ == '__main__':
    import_normals()
    import_analyses()
    import_predictions()
    import_das_predictions()
    print("\nSemua proses impor selesai.")