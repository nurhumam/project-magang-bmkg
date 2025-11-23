import os
import re
import sys
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
PATH_DAS_PROBABILITAS = os.path.join(BASE_PATH, 'data-peluang-das')

# --- KONFIGURASI DATABASE ---
DB_HOST = '127.0.0.1'
DB_DATABASE = 'visualdatabmkg_test'
DB_USERNAME = 'root'
DB_PASSWORD = ''

# Buat koneksi engine sekali saja
engine = create_engine(f'mysql+pymysql://{DB_USERNAME}:{DB_PASSWORD}@{DB_HOST}/{DB_DATABASE}')

# --- Fungsi Generik untuk Impor, Tambah Kolom, Update, dan Index ---
# --- VERSI PERBAIKAN ---
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
                    # --- PERBAIKAN BUG ---
                    # Hapus kolom location HANYA jika full replace
                    print(f"  - Menghapus kolom 'location' lama jika ada ({table_name})...")
                    connection.execute(text(f"ALTER TABLE `{table_name}` DROP COLUMN IF EXISTS `location`"))
                
                elif periods_to_delete:
                    print(f"  - Menghapus data lama ({table_name}: {len(periods_to_delete)} periode)...")
                    
                    delete_query = text(f"DELETE FROM `{table_name}` WHERE data_period IN :periods")

                    connection.execute(delete_query, {'periods': periods_to_delete})

                    # Untuk analisis, kita asumsikan kolom location akan dibuat ulang jika belum ada

                    connection.execute(text(f"ALTER TABLE `{table_name}` DROP COLUMN IF EXISTS `location`"))
                    
                    # # Tentukan kolom periode berdasarkan tabel
                    # period_column_name = 'data_period' # Default untuk analysis
                    # if table_name == 'climate_das_predictions':
                    #     period_column_name = 'prediction_das_period'
                    # elif table_name == 'climate_das_probabilities':
                    #     period_column_name = 'prediction_das_period'
                    
                    # # --- PERBAIKAN KESALAHAN NAMA VARIABEL (PENYEBAB ERROR ANDA) ---
                    # # Menggunakan nama variabel 'period_column_name' yang benar
                    # delete_query = text(f"DELETE FROM `{table_name}` WHERE {period_column_name} IN :periods")
                    # connection.execute(delete_query, {'periods': periods_to_delete})
                    
                    # # --- PERBAIKAN BUG ---
                    # # JANGAN drop kolom location di sini
                
                else:
                    raise ValueError("Harus TRUNCATE atau menyediakan periods_to_delete.")

                # 2. Masukkan data baru (tanpa kolom location)
                print(f"  - Memasukkan {len(df_insert)} baris data baru ({table_name}, chunksize=100)...")
                df_insert.to_sql(table_name, con=connection, if_exists='append', index=False, chunksize=100)

                # --- 3. Buat dan Isi Kolom Spasial ---
                print(f"  - Menambahkan kolom 'location' ({table_name})...")
                connection.execute(text(f"ALTER TABLE `{table_name}` ADD COLUMN IF NOT EXISTS `location` POINT NULL"))

                print(f"  - Mengisi kolom 'location' ({table_name})...")
                where_clause_update = ""
                
                # --- Perbaikan Logika Update Spasial ---
                # Jika kita delete by period, kita HANYA update baris-baris baru
                # if not is_full_replace and periods_to_delete:
                #     # Tentukan kolom periode lagi (untuk blok ini)
                #     period_column_name_update = 'data_period' # Default
                #     if table_name == 'climate_das_predictions':
                #         period_column_name_update = 'prediction_das_period'
                #     elif table_name == 'climate_das_probabilities':
                #         period_column_name_update = 'prediction_das_period'
                        
                #     periods_str = "','".join(periods_to_delete)
                #     # --- PERBAIKAN KESALAHAN NAMA VARIABEL ---
                #     where_clause_update = f"AND {period_column_name_update} IN ('{periods_str}')" 
                
                # Jika full replace, update semua (where_clause_update = "")
                
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

                # 4. Ubah kolom menjadi NOT NULL
                try:
                    print(f"  - Mengubah 'location' menjadi NOT NULL ({table_name})...")
                    connection.execute(text(f"ALTER TABLE `{table_name}` MODIFY COLUMN `location` POINT NOT NULL"))
                except Exception as modify_err:
                    if "Invalid use of NULL value" in str(modify_err) or "Duplicate column name" in str(modify_err):
                        print(f"    -> Peringatan saat MODIFY: {modify_err}. Kemungkinan kolom sudah NOT NULL atau ada NULL tersisa.")
                    else:
                        raise modify_err 


                # 5. Tambahkan SPATIAL INDEX
                print(f"  - Menambahkan SPATIAL INDEX pada 'location' ({table_name})...")
                index_name = f"{table_name}_location_spatialindex"
                connection.execute(text(f"DROP INDEX IF EXISTS `{index_name}` ON `{table_name}`"))
                connection.execute(text(f"ALTER TABLE `{table_name}` ADD SPATIAL INDEX `{index_name}`(`location`)"))

                transaction.commit()
                print(f"Impor & Pembuatan Spasial ({table_name}) BERHASIL.")

            except Exception as e:
                print(f"  - GAGAL ({table_name}): Transaksi dibatalkan. {e}")
                transaction.rollback()
                raise
# --- AKHIR FUNGSI PERBAIKAN ---

def find_latest_das_version_date(path_das_data, file_prefix):
    """
    Mencari tanggal rilis versi prediksi Dasarian terbaru (Senin/Kamis)
    yang benar-benar memiliki file di folder.
    """
    today = datetime.now()
    days_to_check = [0, 3] # Senin (0) dan Kamis (3)
    
    # Cari semua file yang ada di direktori
    all_files_in_dir = os.listdir(path_das_data)
    
    # Iterasi mundur, maksimal 30 hari (sekitar 12 versi rilis)
    for i in range(30): 
        check_date = today - relativedelta(days=i)
        
        # Hanya cek tanggal rilis yang valid (Senin atau Kamis)
        if check_date.weekday() in days_to_check:
            target_version_str = check_date.strftime('%Y.%m.%d') 
            
            # Cek apakah ada file untuk versi ini
            version_files = [
                f for f in all_files_in_dir 
                if f.startswith(file_prefix) and f.endswith(f"_ver_{target_version_str}.csv")
            ]
            
            if version_files:
                return check_date # Versi rilis ditemukan!

    return None


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
        all_numeric_cols = cols_to_round + ['zom_na', 'urut', 'no_grid']

        for col in all_numeric_cols:
            if col in df.columns:
                df[col] = pd.to_numeric(df[col], errors='coerce')

        original_count = len(df)
        df.dropna(subset=['latitude', 'longitude'], inplace=True)
        dropped_count = original_count - len(df)
        if dropped_count > 0:
            print(f"  INFO: Dihapus {dropped_count} baris karena lat/lon kosong/tidak valid.")

        df[cols_to_round] = df[cols_to_round].round(2)

        db_columns = ['urut', 'no_grid', 'longitude', 'latitude', 'zom_1', 'zom_na', 'tipe_zom', 'chthn', 'province', 'regency', 'kecamatan', 'desa', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec']
        kolom_insert = [col for col in df.columns if col in db_columns]
        df_insert = df[kolom_insert].copy()

        if 'latitude' in df_insert.columns:
            df_insert['latitude'] = df_insert['latitude'].astype(float)
        if 'longitude' in df_insert.columns:
            df_insert['longitude'] = df_insert['longitude'].astype(float)

        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)
        import_table_and_create_spatial('climate_normals', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Data Normal: {e}")

def import_analyses():
    print("\n--- Memulai Impor Data Analisis ---")
    try:
        today = datetime.now()
        
        # 1. Tentukan 3 periode YYYYMM target (Bulan lalu, 2 bulan lalu, 3 bulan lalu)
        # Jika sekarang November (bulan 11), target idealnya: Okt (10), Sep (09), Ags (08)
        ideal_target_dates = []
        for i in range(1, 4):
            target_date = today - relativedelta(months=i)
            ideal_target_dates.append(target_date.strftime('%Y%m'))
        
        # ideal_target_dates sekarang berisi [bulan_lalu, 2_bulan_lalu, 3_bulan_lalu]
        # Contoh: [202510, 202509, 202508] (diurutkan mundur)
        print(f"INFO: Periode ideal 3 bulan terakhir: {ideal_target_dates}")

        # 2. Cari semua file yang ada di direktori
        all_files_in_dir = os.listdir(PATH_ANALISIS)
        
        # 3. Kumpulkan semua file yang mengandung periode bulan apa pun di dalamnya
        available_period_files = {} # {YYYYMM: filename}
        for file_name in all_files_in_dir:
            match = re.search(r'(\d{6})', file_name)
            if match:
                period_ym = match.group(1)
                available_period_files[period_ym] = file_name
        
        # 4. Urutkan semua periode yang tersedia dari yang terbaru ke terlama
        # Menggunakan kunci string YYYYMM untuk pengurutan yang benar
        sorted_available_periods = sorted(available_period_files.keys(), reverse=True)
        
        # 5. Ambil 3 periode TERBARU yang benar-benar ADA di folder
        periods_to_process = sorted_available_periods[:3]
        
        if not periods_to_process:
            print("SELESAI: Tidak ada file analisis yang ditemukan.")
            return

        files_to_process = [available_period_files[p] for p in periods_to_process]
        
        print(f"File yang akan diproses (3 periode TERBARU): {sorted(files_to_process)}")

        all_data = []
        periods_to_update_db = []
        
        # Lanjutkan loop pemrosesan file
        for file_name in files_to_process:
            match = re.search(r'(\d{6})', file_name)
            if not match: continue
            
            date_str = match.group(1)
            period = f"{date_str[:4]}-{date_str[4:]}"
            periods_to_update_db.append(period)

            # ... (Sisa kode pembacaan file sama seperti sebelumnya) ...
            file_path = os.path.join(PATH_ANALISIS, file_name)
            # Perhatikan: engine='xlrd' hanya diperlukan untuk file .xls, 
            # jika file Anda .xlsx atau .csv, mungkin perlu disesuaikan. 
            # Saya asumsikan file analisis Anda berformat Excel.
            df = pd.read_excel(file_path, engine='xlrd') 
            df.rename(columns={'LON': 'longitude', 'LAT': 'latitude', 'CH': 'ch'}, inplace=True)

            cols_to_numeric = ['latitude', 'longitude', 'ch']
            for col in cols_to_numeric:
                df[col] = pd.to_numeric(df[col], errors='coerce')

            original_count = len(df)
            df.dropna(subset=['latitude', 'longitude'], inplace=True)
            dropped_count = original_count - len(df)
            if dropped_count > 0:
                print(f"  INFO ({file_name}): Dihapus {dropped_count} baris lat/lon tidak valid.")

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
        
        # Catatan: Perlu dipastikan di sini bahwa get_period_column('climate_analyses') 
        # mengembalikan 'data_period' jika Anda menggunakan perbaikan Bug 3
        import_table_and_create_spatial('climate_analyses', df_insert, is_full_replace=False, periods_to_delete=unique_periods)

    except Exception as e:
        print(f"GAGAL Impor Analisis: {e}")

def import_predictions():
    print("\n--- Memulai Impor Data Prediksi Bulanan ---")
    try:
        today = datetime.now()
        
        # Cari semua file prediksi yang ada
        all_files = [f for f in os.listdir(PATH_PREDIKSI) if f.startswith('pch_ensMean') and f.endswith('.csv')]
        
        if not all_files:
            print("INFO: Tidak ada file prediksi bulanan yang ditemukan.")
            return

        # 1. Kumpulkan semua versi unik (YYYY.MM.DD) yang tersedia
        available_versions = {} # {YYYY.MM.DD: [list_of_filenames]}
        
        for file_name in all_files:
            # Match versi: _ver_(\d{4})\.(\d{2})\.(\d{2})
            match = re.search(r'_ver_(\d{4})\.(\d{2})\.(\d{2})', file_name)
            if match:
                version_str = f"{match.group(1)}.{match.group(2)}.{match.group(3)}"
                if version_str not in available_versions:
                    available_versions[version_str] = []
                available_versions[version_str].append(file_name)

        if not available_versions:
            print("SELESAI: File prediksi ditemukan, tetapi tidak ada versi yang valid.")
            return

        # 2. Urutkan versi secara terbalik (terbaru dahulu)
        # Versi adalah string YYYY.MM.DD, pengurutan string akan berfungsi
        sorted_versions = sorted(available_versions.keys(), reverse=True)
        
        # 3. Temukan VERSI TERBARU yang akan digunakan
        latest_version_str = sorted_versions[0]
        files_to_process = available_versions[latest_version_str]
        target_version_db = latest_version_str.replace('.', '-')
        
        print(f"INFO: Versi prediksi bulanan TERBARU yang tersedia adalah: {target_version_db}")
        
        # 4. Filter file untuk memastikan hanya 6 periode prediksi ke depan yang diambil
        
        # Kumpulkan semua periode prediksi (YYYY-MM) dari file dalam versi terbaru
        period_data = {} # {period_db_format: file_name}
        for file_name in files_to_process:
            # Match periode prediksi: (\d{4})\.(\d{2})
            match = re.search(r'pch_ensMean\.(\d{4})\.(\d{2})_', file_name)
            if match:
                period_db = f"{match.group(1)}-{match.group(2)}"
                period_data[period_db] = file_name
                
        # Urutkan periode prediksi dari yang terlama ke yang terbaru
        sorted_periods = sorted(period_data.keys())
        
        # Ambil 6 periode pertama yang tersedia
        periods_to_process_db = sorted_periods[:6]
        
        if len(periods_to_process_db) < 6:
            print(f"PERINGATAN: Hanya ditemukan {len(periods_to_process_db)} dari 6 periode prediksi di versi ini.")

        all_data = []
        
        for period in periods_to_process_db:
            file_name = period_data[period]
            file_path = os.path.join(PATH_PREDIKSI, file_name)
            
            print(f"PROSES: Mempersiapkan file '{file_name}' (Periode: {period})...")

            # --- Mulai Proses Pembacaan Data ---
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
                print(f"  INFO ({file_name}): Dihapus {dropped_count} baris lat/lon tidak valid.")

            df[cols_to_round] = df[cols_to_round].round(2)

            df['prediction_period'] = period
            df['prediction_version'] = target_version_db
            all_data.append(df)
        
        if not all_data:
            print(f"SELESAI: Tidak ada data valid yang diproses untuk versi {target_version_db}.")
            return

        final_df = pd.concat(all_data, ignore_index=True)

        db_columns = ['no_grid', 'longitude', 'latitude', 'val', 'MIN', 'MAX', 'prediction_period', 'prediction_version']
        kolom_insert = [col for col in final_df.columns if col in db_columns]
        df_insert = final_df[kolom_insert].copy()

        # ... (Penanganan tipe data dan impor sama seperti sebelumnya) ...
        if 'latitude' in df_insert.columns:
            df_insert['latitude'] = df_insert['latitude'].astype(float)
        if 'longitude' in df_insert.columns:
            df_insert['longitude'] = df_insert['longitude'].astype(float)

        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)
        
        # Gunakan Full Replace untuk prediksi bulanan
        import_table_and_create_spatial('climate_predictions', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Prediksi Bulanan: {e}")

def import_das_predictions():
    print("\n--- Memulai Impor Data Prediksi Dasarian ---")
    try:
        target_version_date_obj = find_latest_das_version_date(PATH_DAS_PREDIKSI, 'pch_det')
        
        if not target_version_date_obj:
            print("SELESAI: Tidak ada file prediksi dasarian yang tersedia untuk Senin/Kamis manapun dalam 30 hari terakhir.")
            return

        target_version_str = target_version_date_obj.strftime('%Y.%m.%d') 
        target_version_db = target_version_date_obj.strftime('%Y-%m-%d') 

        print(f"INFO: Menggunakan versi prediksi dasarian terbaru yang ditemukan: {target_version_db}")

        # Cari semua file untuk versi yang ditemukan
        files = [f for f in os.listdir(PATH_DAS_PREDIKSI) if f.startswith('pch_det') and f.endswith(f"_ver_{target_version_str}.csv")]
        
        # ... (Sisa logika pengumpulan data, concat, dan import_table_and_create_spatial tetap sama)
        
        if not files:
            # Seharusnya tidak terjadi karena find_latest_das_version_date sudah memastikan keberadaan file
            print(f"SELESAI: File versi {target_version_str} hilang setelah pengecekan awal.")
            return

        all_data = []
        for file_name in files:
            match = re.search(r'pch_det\.(\d{4})\.(\d{2})\.das\.(\d)', file_name)
            if not match:
                print(f" WARNING: Melewatkan file dengan format aneh: {file_name}")
                continue

            year = match.group(1)
            month = match.group(2)
            das = match.group(3)
            prediction_das_period = f"{year}-{int(month):02d}-{das}"
            
            print(f" - Memproses: {file_name} (Periode: {prediction_das_period})")

            file_path = os.path.join(PATH_DAS_PREDIKSI, file_name)
            df = pd.read_csv(file_path, skiprows=1, header=None, names=['NOGRID', 'LON', 'LAT', 'VAL', 'MIN', 'MAX', 'SH', 'CHp'])
            
            df.rename(columns={'NOGRID':'no_grid', 'LON': 'longitude', 'LAT': 'latitude', 'VAL': 'val'}, inplace=True)
            cols_to_round = ['longitude', 'latitude', 'val', 'MIN', 'MAX', 'SH', 'CHp']
            all_numeric_cols = cols_to_round + ['no_grid']

            for col in all_numeric_cols:
                if col in df.columns:
                    df[col] = pd.to_numeric(df[col], errors='coerce')

            df.dropna(subset=['latitude', 'longitude'], inplace=True)
            df[cols_to_round] = df[cols_to_round].round(2)

            df['prediction_das_period'] = prediction_das_period
            df['prediction_version'] = target_version_db
            all_data.append(df)

        if not all_data:
            print("SELESAI: Tidak ada data valid yang diproses.")
            return

        final_df = pd.concat(all_data, ignore_index=True)

        db_columns = ['no_grid', 'longitude', 'latitude', 'val', 'MIN', 'MAX', 'SH', 'CHp', 'prediction_das_period', 'prediction_version']
        kolom_insert = [col for col in final_df.columns if col in db_columns]
        df_insert = final_df[kolom_insert].copy()

        df_insert['latitude'] = df_insert['latitude'].astype(float)
        df_insert['longitude'] = df_insert['longitude'].astype(float)
        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)
        
        import_table_and_create_spatial('climate_das_predictions', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Prediksi Dasarian: {e}")

def import_das_probabilities():
    print("\n--- Memulai Impor Data Peluang Dasarian ---")
    try:
        # Panggil fungsi helper baru untuk mencari versi yang tersedia
        target_version_date_obj = find_latest_das_version_date(PATH_DAS_PROBABILITAS, 'pch_prob')
        
        if not target_version_date_obj:
            print("SELESAI (Peluang): Tidak ada file peluang dasarian yang tersedia untuk Senin/Kamis manapun dalam 30 hari terakhir.")
            return

        target_version_str = target_version_date_obj.strftime('%Y.%m.%d') 
        target_version_db = target_version_date_obj.strftime('%Y-%m-%d') 

        print(f"INFO (Peluang): Menggunakan versi peluang dasarian terbaru yang ditemukan: {target_version_db}")

        # Cari semua file untuk versi yang ditemukan
        files = [f for f in os.listdir(PATH_DAS_PROBABILITAS) if f.startswith('pch_prob') and f.endswith(f"_ver_{target_version_str}.csv")]
        
        # ... (Sisa logika pengumpulan data, concat, dan import_table_and_create_spatial tetap sama)

        if not files:
            print(f"SELESAI (Peluang): File versi {target_version_str} hilang setelah pengecekan awal.")
            return

        all_data = []
        for file_name in files:
            match = re.search(r'pch_prob\.(\d{4})\.(\d{2})\.das\.(\d)', file_name)
            if not match:
                print(f" WARNING (Peluang): Melewatkan file dengan format aneh: {file_name}")
                continue

            year = match.group(1)
            month = match.group(2)
            das = match.group(3)
            prediction_das_period = f"{year}-{int(month):02d}-{das}"
            
            print(f" - Memproses (Peluang): {file_name} (Periode: {prediction_das_period})")

            file_path = os.path.join(PATH_DAS_PROBABILITAS, file_name)
            df = pd.read_csv(file_path)
            
            df.rename(columns={'NOGRID':'no_grid', 'LON': 'longitude', 'LAT': 'latitude'}, inplace=True)
            
            cols_to_round = [
                'longitude', 'latitude', 'b20', 'b50', 'b100', 'b150', 
                'a20', 'a50', 'a100', 'a150', 'a200', 'a300'
            ]
            all_numeric_cols = cols_to_round + ['no_grid']

            for col in all_numeric_cols:
                if col in df.columns:
                    df[col] = pd.to_numeric(df[col], errors='coerce')
                else:
                    print(f"  WARNING (Peluang): Kolom '{col}' tidak ditemukan di {file_name}")

            df.dropna(subset=['latitude', 'longitude'], inplace=True)
            df[cols_to_round] = df[cols_to_round].round(2)

            df['prediction_das_period'] = prediction_das_period
            df['prediction_version'] = target_version_db
            all_data.append(df)

        if not all_data:
            print("SELESAI (Peluang): Tidak ada data valid yang diproses.")
            return

        final_df = pd.concat(all_data, ignore_index=True)

        db_columns = [
            'no_grid', 'longitude', 'latitude', 'b20', 'b50', 'b100', 'b150', 
            'a20', 'a50', 'a100', 'a150', 'a200', 'a300', 
            'prediction_das_period', 'prediction_version'
        ]
        
        kolom_insert = [col for col in final_df.columns if col in db_columns]
        df_insert = final_df[kolom_insert].copy()

        df_insert['latitude'] = df_insert['latitude'].astype(float)
        df_insert['longitude'] = df_insert['longitude'].astype(float)
        df_insert = df_insert.astype(object).where(pd.notnull(df_insert), None)
        
        import_table_and_create_spatial('climate_das_probabilities', df_insert, is_full_replace=True)

    except Exception as e:
        print(f"GAGAL Impor Peluang Dasarian: {e}")
        

if __name__ == '__main__':
    args = sys.argv[1:]
    
    if 'dasarian' in args:
        import_das_predictions()
        import_das_probabilities()
        print("\nSinkronisasi Dasarian selesai.")
    else:
        import_normals()
        import_analyses()
        import_predictions()
        print("\nSemua proses impor selesai.")