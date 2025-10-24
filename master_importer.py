import os
import re
import pandas as pd
from sqlalchemy import create_engine, text
from dateutil.relativedelta import relativedelta
from datetime import datetime

# --- KONFIGURASI PATH ---
BASE_PATH = 'C:/Users/USER/Documents/Arsip-Magang/BMKG/web-chat/web-informasi-iklim/data'
PATH_NORMAL = os.path.join(BASE_PATH, 'data-rata-rata', 'Grid_Desa_20251021_Table.xls')
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
            'URUT': 'urut', 'NO_GRID': 'nogrid', 'LAT': 'latitude', 'LON': 'longitude', 
            'ZOM9120__1': 'zom_1','ZOM9120__NA': 'zom_na', 'TIPE_ZOM': 'tipe_zom', 'CHTHN': 'chthn', 
            'ID_PROV38': 'province', 'ID_KABKOTA_IKN': 'regency', 'Kecamatan': 'kecamatan', 'Kelurahan/Desa': 'desa',
            'JAN': 'jan', 'FEB': 'feb', 'MAR': 'mar', 'APR': 'apr', 'MAY': 'may', 'JUN': 'jun', 
            'JUL': 'jul', 'AUG': 'aug', 'SEP': 'sep', 'OCT': 'oct', 'NOV': 'nov', 'DEC': 'dec'
        }, inplace=True)

        cols_to_round = [
            'latitude', 'longitude', 'chthn', 'jan', 'feb', 'mar', 'apr', 'may', 
            'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'
        ]
        for col in cols_to_round:
            df[col] = pd.to_numeric(df[col], errors='coerce')
        df[cols_to_round] = df[cols_to_round].round(2)

        df.to_sql('climate_normals', con=engine, if_exists='replace', index=False)
        print("Impor Data Normal berhasil (data dibulatkan 2 desimal).")
    except Exception as e:
        print(f"GAGAL Impor Data Normal: {e}")

def import_analyses():
    print("\n--- Memulai Impor Data Analisis (3 Bulan Sebelum Bulan Ini) ---")
    try:
        today = datetime.now()
        
        # ==== Tentukan periode 3 bulan target sebelum bulan ini ====
        target_periods_ym = []
        for i in range(1, 4): 
            target_date = today - relativedelta(months=i)
            target_periods_ym.append(target_date.strftime('%Y%m'))
        
        target_periods_ym.sort() 
        print(f"INFO: Mencari file analisis untuk periode target: {target_periods_ym}")

        # ==== Cari file yang cocok dengan periode target ====
        all_files_in_dir = os.listdir(PATH_ANALISIS)
        files_to_process = []
        for file_name in all_files_in_dir:
            match = re.search(r'(\d{6})', file_name)
            if match and match.group(1) in target_periods_ym:
                files_to_process.append(file_name)
        
        if not files_to_process:
            print("SELESAI: Tidak ada file analisis yang cocok dengan periode target yang ditemukan.")
            return

        print(f"File yang akan diproses: {sorted(files_to_process)}")

        # ==== Proses file yang ditemukan ====
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

            cols_to_round = ['latitude', 'longitude', 'ch']
            for col in cols_to_round:
                df[col] = pd.to_numeric(df[col], errors='coerce')
            df[cols_to_round] = df[cols_to_round].round(2)

            df['data_period'] = period
            all_data.append(df[['latitude', 'longitude', 'ch', 'data_period']])

        final_df = pd.concat(all_data, ignore_index=True)

        # ==== Lakukan "hapus-lalu-tambah" yang aman dalam transaksi ====
        with engine.connect() as connection:
            with connection.begin() as transaction:
                try:
                    unique_periods = list(set(periods_to_update_db))
                    print(f"  - Menghapus data lama untuk periode: {unique_periods}...")
                    delete_query = text("DELETE FROM climate_analyses WHERE data_period IN :periods")
                    connection.execute(delete_query, {'periods': unique_periods})

                    print(f"  - Memasukkan {len(final_df)} baris data baru...")
                    final_df.to_sql('climate_analyses', con=connection, if_exists='append', index=False)
                    
                    transaction.commit()
                    print("  - Transaksi berhasil.")
                except Exception as e:
                    print(f"  - GAGAL: Terjadi error, membatalkan transaksi... {e}")
                    transaction.rollback()
                    raise

        print(f"BERHASIL: Impor data analisis untuk 3 bulan terakhir telah selesai (data dibulatkan 2 desimal).")
    except Exception as e:
        print(f"GAGAL: Terjadi kesalahan besar pada proses impor analisis: {e}")

def import_predictions():
    print("\n--- Memulai Impor Data Prediksi (Hapus Total & Ganti Dengan Bulan Ini) ---")
    try:
        current_month = datetime.now().month
        current_year = datetime.now().year
        print(f"INFO: Skrip berjalan pada bulan {current_month}/{current_year}. Hanya file versi bulan ini yang akan diimpor.")

        files = [f for f in os.listdir(PATH_PREDIKSI) if f.startswith('pch_ensMean') and f.endswith('.csv')]
        if not files:
            print("INFO: Tidak ada file prediksi ditemukan di direktori.")
            return

        all_data = []
        for file_name in files:
            match = re.search(r'(\d{4})\.(\d{2})_ver_(\d{4})\.(\d{2})\.(\d{2})', file_name)
            if not match:
                continue

            # Logika filter berdasarkan bulan ini tetap dipertahankan
            file_version_month = int(match.group(4))
            if file_version_month != current_month:
                print(f"INFO: Melewatkan '{file_name}' karena versi bulan ({file_version_month}) tidak cocok dengan bulan ini ({current_month}).")
                continue
            
            print(f"PROSES: Mempersiapkan file '{file_name}'...")
            period = f"{match.group(1)}-{match.group(2)}"
            version = f"{match.group(3)}-{match.group(4)}-{match.group(5)}"
            
            file_path = os.path.join(PATH_PREDIKSI, file_name)
            df = pd.read_csv(file_path, skiprows=1, header=None, names=['NOGRID', 'LON', 'LAT', 'VAL', 'MIN', 'MAX'])
            df.rename(columns={'LON': 'longitude', 'LAT': 'latitude', 'VAL': 'val'}, inplace=True)
            
            cols_to_round = ['longitude', 'latitude', 'val', 'MIN', 'MAX']
            for col in cols_to_round:
                df[col] = pd.to_numeric(df[col], errors='coerce')
            df[cols_to_round] = df[cols_to_round].round(2)
            
            df['prediction_period'] = period
            df['prediction_version'] = version
            all_data.append(df)

        if not all_data:
            print("SELESAI: Tidak ada file prediksi valid untuk bulan ini yang ditemukan.")
            return

        final_df = pd.concat(all_data, ignore_index=True)

        print(f"\MENGHAPUS SELURUH DATA LAMA di tabel 'climate_predictions' dan mengunggah {len(final_df)} baris data baru...")
        final_df.to_sql('climate_predictions', con=engine, if_exists='replace', index=False)

        print(f"BERHASIL: Tabel 'climate_predictions' telah diganti total dengan data prediksi untuk bulan {current_month} (data dibulatkan 2 desimal).")

    except Exception as e:
        print(f"GAGAL: Terjadi kesalahan pada proses impor prediksi: {e}")

if __name__ == '__main__':
    import_normals()
    import_analyses()
    import_predictions()
    print("\nSemua proses impor selesai.")