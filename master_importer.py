import os
import re
import pandas as pd
from sqlalchemy import create_engine, text

# --- KONFIGURASI PATH ---
BASE_PATH = 'C:/Users/USER/Documents/Arsip-Magang/BMKG/web-chat/data'
PATH_NORMAL = os.path.join(BASE_PATH, 'data-rata-rata', 'ID_GRID_CHPROVKAB_INDO_NORMAL9120.xlsx')
PATH_ANALISIS = os.path.join(BASE_PATH, 'data-analisis')
PATH_PREDIKSI = os.path.join(BASE_PATH, 'data-prediksi')

# --- KONFIGURASI DATABASE ---
DB_HOST = '127.0.0.1'
DB_DATABASE = 'visualdatabmkg_test' # Sesuaikan dengan nama DB Anda
DB_USERNAME = 'root'
DB_PASSWORD = ''

# Buat koneksi engine sekali saja
engine = create_engine(f'mysql+pymysql://{DB_USERNAME}:{DB_PASSWORD}@{DB_HOST}/{DB_DATABASE}')

def import_normals():
    print("\n--- Memulai Impor Data Normal ---")
    try:
        df = pd.read_excel(PATH_NORMAL)
        # Ganti nama kolom agar sesuai dengan tabel DB
        df.rename(columns={
            'LAT': 'latitude', 'LON': 'longitude', 'ID_PROV38': 'province', 
            'ID_KABKOTA_IKN': 'regency', 'JAN': 'jan', 'FEB': 'feb', 'MAR': 'mar', 
            'APR': 'apr', 'MAY': 'may', 'JUN': 'jun', 'JUL': 'jul', 'AUG': 'aug', 
            'SEP': 'sep', 'OCT': 'oct', 'NOV': 'nov', 'DEC': 'dec'
        }, inplace=True)
        # Hapus data lama dan ganti dengan yang baru
        df.to_sql('climate_normals', con=engine, if_exists='replace', index=False)
        print("Impor Data Normal berhasil.")
    except Exception as e:
        print(f"GAGAL Impor Data Normal: {e}")

def import_analyses():
    print("\n--- Memulai Impor Data Analisis (3 Bulan Terakhir) ---")
    try:
        files = [f for f in os.listdir(PATH_ANALISIS) if f.startswith('BlendGSMAP_POS') and f.endswith('.xls')]
        files.sort(reverse=True)
        
        files_to_process = files[:3]
        if not files_to_process:
            print("Tidak ada file analisis ditemukan.")
            return

        files_to_process.reverse()
        print(f"File yang diproses: {[f for f in files_to_process]}")
        
        all_data = []
        periods_to_update = []
        for file_name in files_to_process:
            match = re.search(r'(\d{6})', file_name)
            if not match: continue
            
            date_str = match.group(1)
            period = f"{date_str[:4]}-{date_str[4:]}"
            periods_to_update.append(period)

            file_path = os.path.join(PATH_ANALISIS, file_name)
            df = pd.read_excel(file_path, engine='xlrd')
            df.rename(columns={'LON': 'longitude', 'LAT': 'latitude', 'CH': 'ch'}, inplace=True)
            df['data_period'] = period
            all_data.append(df[['latitude', 'longitude', 'ch', 'data_period']])

        final_df = pd.concat(all_data, ignore_index=True)

        with engine.connect() as connection:
            # Hapus data untuk periode ini, lalu masukkan yang baru
            delete_query = text("DELETE FROM climate_analyses WHERE data_period IN :periods")
            connection.execute(delete_query, {'periods': periods_to_update})
            final_df.to_sql('climate_analyses', con=connection, if_exists='append', index=False)
            connection.commit()
        print(f"Impor Data Analisis berhasil: {len(final_df)} baris.")
    except Exception as e:
        print(f"GAGAL Impor Data Analisis: {e}")

def import_predictions():
    print("\n--- Memulai Impor Data Prediksi ---")
    try:
        files = [f for f in os.listdir(PATH_PREDIKSI) if f.startswith('pch_ensMean') and f.endswith('.csv')]
        if not files:
            print("INFO: Tidak ada file prediksi ditemukan.")
            return
            
        print(f"File yang akan diproses: {files}")
        
        all_data = []
        versions_found = set()
        for file_name in files:
            # DIUBAH: Regex diperbarui untuk menangkap periode dan versi
            # Pola: YYYY.MM_ver_YYYY.MM.DD
            match = re.search(r'(\d{4})\.(\d{2})_ver_(\d{4})\.(\d{2})\.(\d{2})', file_name)
            
            if not match:
                print(f"INFO: Melewatkan file dengan format nama tidak cocok: {file_name}")
                continue
            
            # Ekstrak periode dan versi dari grup regex
            period = f"{match.group(1)}-{match.group(2)}" # Contoh: 2025-08
            version = f"{match.group(3)}-{match.group(4)}-{match.group(5)}" # Contoh: 2025-08-01
            versions_found.add(version)

            file_path = os.path.join(PATH_PREDIKSI, file_name)
            
            df = pd.read_csv(
                file_path, 
                skiprows=1, 
                header=None, 
                names=['NOGRID', 'LON', 'LAT', 'VAL', 'MIN', 'MAX']
            )
            
            df.rename(columns={'LON': 'longitude', 'LAT': 'latitude', 'VAL': 'val'}, inplace=True)
            
            # Tambahkan kolom periode dan kolom versi yang baru
            df['prediction_period'] = period
            df['prediction_version'] = version # <--- KOLOM BARU DITAMBAHKAN DI SINI
            
            all_data.append(df)
        
        if not all_data:
            print("INFO: Tidak ada file prediksi valid yang berhasil diproses.")
            return

        final_df = pd.concat(all_data, ignore_index=True)
        
        # Kolom baru 'prediction_version' akan otomatis dibuat saat 'replace'
        final_df.to_sql('climate_predictions', con=engine, if_exists='replace', index=False)
        
        print(f"BERHASIL: Impor Data Prediksi selesai: {len(final_df)} baris.")
        print(f"Versi yang ditemukan dan diimpor: {sorted(list(versions_found))}")
        
    except Exception as e:
        print(f"GAGAL: Impor Data Prediksi: {e}")

if __name__ == '__main__':
    import_normals()
    import_analyses()
    import_predictions()
    print("\nSemua proses impor selesai.")