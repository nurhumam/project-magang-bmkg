import os
import pandas as pd
from sqlalchemy import create_engine, text
from google.oauth2 import service_account
from googleapiclient.discovery import build
from io import BytesIO
import re

# --- KONFIGURASI ---
SERVICE_ACCOUNT_FILE = 'kredensial-google.json' 
FOLDER_ID = '1ufH1OqmvPIokn-h8LbkdeJk4qNr6K5r3' # ID Folder Anda

DB_HOST = os.getenv('DB_HOST', '127.0.0.1')
DB_DATABASE = os.getenv('DB_DATABASE', 'laravel')
DB_USERNAME = os.getenv('DB_USERNAME', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')
TABLE_NAME = 'historical_climate_data'

def get_drive_service():
    creds = service_account.Credentials.from_service_account_file(
        SERVICE_ACCOUNT_FILE, scopes=['https://www.googleapis.com/auth/drive'])
    return build('drive', 'v3', credentials=creds)

def main():
    print("Memulai proses sinkronisasi dari Google Drive...")
    service = get_drive_service()

    query = f"'{FOLDER_ID}' in parents and trashed=false"
    results = service.files().list(q=query, pageSize=100, fields="files(id, name, mimeType)").execute()

    file_pattern = re.compile(r'BlendGSMAP_POS\.(\d{6})')
    valid_files = [f for f in results.get('files', []) if file_pattern.match(f['name'])]
    
    files = sorted(valid_files, key=lambda x: x['name'], reverse=True)

    if not files:
        print("Tidak ada file dengan format 'BlendGSMAP_POS.YYYYMM' ditemukan.")
        return

    files_to_process = files[:2]
    files_to_process.reverse()

    print(f"File yang akan diproses (diurutkan dari terlama ke terbaru): {[f['name'] for f in files_to_process]}")
    
    
    all_data_frames = []
    periods_to_update = []

    for file_item in files_to_process:
        file_id = file_item['id']
        file_name = file_item['name']

        match = re.search(r'BlendGSMAP_POS\.(\d{6})', file_name)
        if not match: continue
        
        date_str = match.group(1)
        period = f"{date_str[:4]}-{date_str[4:]}"
        periods_to_update.append(period)

        print(f"Mengunduh dan memproses '{file_name}' untuk periode {period}...")
        
        if 'google-apps.spreadsheet' in file_item['mimeType']:
            request = service.files().export_media(fileId=file_id, mimeType='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            file_content = BytesIO(request.execute())
            df = pd.read_excel(file_content, engine='openpyxl')
        else:
            request = service.files().get_media(fileId=file_id)
            file_content = BytesIO(request.execute())
            df = pd.read_excel(file_content, engine='xlrd')
        
        df.rename(columns={'LON': 'longitude', 'LAT': 'latitude', 'CH': 'ch', 'SH%': 'sh_percent', 'SHpercentil': 'sh_percentil'}, inplace=True)
        df['data_period'] = period
        all_data_frames.append(df)

    if not all_data_frames:
        print("Tidak ada data valid untuk diimpor.")
        return

    final_df = pd.concat(all_data_frames, ignore_index=True)
    engine = create_engine(f'mysql+pymysql://{DB_USERNAME}:{DB_PASSWORD}@{DB_HOST}/{DB_DATABASE}')

    with engine.connect() as connection:
        # DIUBAH: Mengosongkan seluruh tabel sebelum memasukkan data baru
        print(f"Mengosongkan tabel '{TABLE_NAME}'...")
        truncate_query = text(f"TRUNCATE TABLE {TABLE_NAME}")
        connection.execute(truncate_query)
        connection.commit()

        print(f"Memasukkan {len(final_df)} baris data baru...")
        final_df.to_sql(TABLE_NAME, con=connection, if_exists='append', index=False)
        connection.commit()

    print("Sinkronisasi selesai dengan sukses!")

if __name__ == '__main__':
    main()