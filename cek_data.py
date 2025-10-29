import pandas as pd
import numpy as np
import os

BASE_PATH = 'C:/Users/USER/Documents/Arsip-Magang/BMKG/web-chat/web-informasi-iklim/data'
PATH_NORMAL = os.path.join(BASE_PATH, 'data-rata-rata', 'Grid_Desa_20251021_Table.xls')

print(f"Menganalisis file: {PATH_NORMAL} ...")

try:
    # 1. Baca excel, paksa lat/lon sebagai string
    df = pd.read_excel(PATH_NORMAL, dtype={'LAT': str, 'LON': str})
    
    # Simpan data asli untuk referensi
    df['LAT_ASLI'] = df['LAT']
    df['LON_ASLI'] = df['LON']

    # 2. Bersihkan spasi
    df['LAT'] = df['LAT'].str.strip()
    df['LON'] = df['LON'].str.strip()
    
    # 3. Ganti string kosong '' menjadi NaN
    df.replace('', np.nan, inplace=True)
    
    # 4. Paksa konversi ke numerik, semua yg "buruk" (teks, dll) akan menjadi NaN
    df['LAT_NUM'] = pd.to_numeric(df['LAT'], errors='coerce')
    df['LON_NUM'] = pd.to_numeric(df['LON'], errors='coerce')

    # 5. Temukan semua baris di mana lat/lon asli ADA, tapi GAGAL dikonversi
    # atau di mana lat/lon asli tidak ada (NaN)
    
    # pd.isnull(df['LAT_NUM']) -> Gagal konversi ATAU memang kosong
    # pd.notnull(df['LAT_ASLI']) -> Memastikan ini bukan baris kosong biasa
    
    bad_lat = df[pd.isnull(df['LAT_NUM']) & pd.notnull(df['LAT_ASLI'])]
    bad_lon = df[pd.isnull(df['LON_NUM']) & pd.notnull(df['LON_ASLI'])]

    bad_rows = pd.concat([bad_lat, bad_lon]).drop_duplicates()

    if bad_rows.empty:
        print("\n--- HASIL ---")
        print("✅ Analisis selesai. Tidak ditemukan data 'kotor' (spasi atau teks) di kolom LAT/LON.")
        print("Ini aneh. Jika masih error, coba periksa nilai 'infinity'.")
    else:
        print(f"\n--- HASIL ---")
        print(f"🚨 DITEMUKAN {len(bad_rows)} BARIS 'KOTOR' YANG MENYEBABKAN ERROR 1416:")
        
        # Tampilkan hanya kolom yang relevan
        print(bad_rows[['URUT', 'LAT_ASLI', 'LON_ASLI']])

except Exception as e:
    print(f"Gagal membaca file: {e}")