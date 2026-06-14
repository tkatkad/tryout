# **Perancangan Scenario-Based Predictive Simulation Engine untuk Estimasi Passing Grade Seleksi Sekolah Berbasis Distribusi Permintaan Kandidat**

## Abstrak

Prediksi nilai batas bawah penerimaan (*passing grade*) pada sistem seleksi sekolah merupakan permasalahan yang tidak hanya bergantung pada kemampuan akademik individu, tetapi juga dipengaruhi oleh perilaku kolektif peserta, kapasitas kuota, preferensi pilihan sekolah, dan distribusi kualitas kandidat.

Pendekatan simulasi berbasis skenario dapat digunakan untuk memperkirakan perubahan tingkat persaingan dengan membangun model perilaku kandidat secara sintetis. Sistem ini menggabungkan konsep *scenario-driven simulation*, *synthetic population modeling*, *weighted preference distribution*, *constraint-based allocation*, dan *rule-based decision analysis*.

Model menghasilkan estimasi passing grade pada beberapa kondisi persaingan, kemudian mengklasifikasikan tingkat peluang kandidat berdasarkan nilai individu terhadap hasil simulasi. *#computational modeling*.

---

# 1. Pendahuluan

Dalam proses penerimaan siswa baru, nilai terendah yang diterima pada suatu sekolah bukan merupakan angka yang berdiri sendiri.

Nilai tersebut merupakan hasil interaksi:

* jumlah kandidat,
* kualitas kandidat,
* pilihan sekolah,
* kapasitas kursi,
* dan distribusi nilai peserta.

Secara matematis, passing grade dapat dipandang sebagai titik batas (*cut-off point*) dari populasi kandidat yang berhasil memperoleh kursi.

Jika:

* jumlah kandidat bernilai tinggi meningkat,
* sementara kuota tetap,

maka batas penerimaan akan bergerak naik.

Sebaliknya, jika kandidat berkualitas tinggi memilih sekolah lain, maka batas dapat turun.

Karena faktor perilaku manusia sulit diprediksi secara pasti, digunakan pendekatan simulasi berbasis skenario.

---

# 2. Model Konseptual Sistem

Arsitektur sistem terdiri dari beberapa lapisan proses:

```
Input User
    |
    v
Scenario Selection
    |
    v
Demand Simulation
    |
    v
Candidate Distribution
    |
    v
School Competition Model
    |
    v
Passing Grade Estimation
    |
    v
Risk Classification
```

Setiap tahap memiliki fungsi tertentu dalam menghasilkan prediksi.

---

# 3. Scenario Selection sebagai Finite State Model

Antarmuka menyediakan tiga kondisi:

* SEPI
* NORMAL
* RAMAI

Ketiga pilihan tersebut dapat dimodelkan sebagai *finite state machine*.

Setiap state memiliki parameter jumlah kandidat potensial yang melakukan migrasi pilihan.

Contoh:

```
STATE_SEPI

top_candidate = 70


STATE_NORMAL

top_candidate = 120


STATE_RAMAI

top_candidate = 250
```

Perubahan state tidak mengubah algoritma utama, tetapi hanya mengganti parameter simulasi.

Konsep ini dikenal sebagai:

**Parameterized Simulation Model**

yaitu satu mesin simulasi yang mampu menghasilkan banyak kondisi berdasarkan parameter.

---

# 4. Synthetic Population Modeling

Karena daftar kandidat masa depan belum tersedia, sistem membangun populasi sintetis.

Populasi sintetis bukan berarti membuat data individu nyata, melainkan membentuk representasi statistik berdasarkan pola distribusi.

Contoh:

Kelompok nilai tinggi:

```
Nilai >280 : 50 kandidat
Nilai >270 : 70 kandidat
Nilai >260 : 100 kandidat
```

Kemudian sistem menentukan berapa proporsi yang diasumsikan mencoba masuk ke wilayah tertentu.

Misalnya:

```
SEPI:
70 kandidat

NORMAL:
120 kandidat

RAMAI:
250 kandidat
```

Pendekatan ini banyak digunakan dalam:

* simulasi ekonomi,
* perencanaan kota,
* analisis transportasi,
* prediksi permintaan pasar.

---

# 5. Demand Migration Model

Salah satu faktor utama bukan hanya kemampuan kandidat, tetapi keputusan memilih sekolah.

Tidak semua siswa dengan nilai tinggi akan memilih sekolah tertentu.

Oleh karena itu digunakan:

## Weighted Preference Distribution

Contoh distribusi:

```
SMP A : 40%
SMP B : 28%
SMP C : 16%
SMP D : 8%
Lainnya : 8%
```

Distribusi ini menggambarkan kecenderungan preferensi.

Model tidak mengasumsikan:

"semua siswa tinggi masuk sekolah favorit"

tetapi:

"semakin tinggi daya tarik sekolah, semakin besar probabilitas dipilih".

---

# 6. Competition Allocation Model

Setelah kandidat sintetis terbentuk, dilakukan simulasi perebutan kursi.

Setiap sekolah memiliki:

* daftar kandidat,
* nilai kandidat,
* kuota.

Contoh:

```
SMP A

Kuota:
33 kursi


Kandidat:

295
292
290
288
287
...
```

Kemudian dilakukan:

1. pengurutan nilai menurun (*descending sort*)
2. pemilihan kandidat sebanyak kuota
3. menentukan kandidat terakhir yang diterima

Nilai kandidat terakhir tersebut menjadi:

```
Passing Grade Prediction
```

---

# 7. Top-K Selection Algorithm

Secara algoritmik prosesnya:

```
Input:
daftar nilai kandidat

Sort descending

Ambil K kandidat

Nilai kandidat ke-K
=
prediksi passing grade
```

Jika:

```
K = jumlah kursi
```

maka:

```
PG = kandidat terakhir dalam daftar penerimaan
```

Konsep ini merupakan:

**Top-K Selection Algorithm**

yang banyak digunakan pada:

* ranking search engine,
* rekomendasi produk,
* sistem kompetisi.

---

# 8. Risk Classification System

Setelah passing grade diprediksi, nilai user dibandingkan.

Contoh:

```
nilai_user = 285

PG SMP A = 290
```

Maka:

```
nilai_user < PG
```

status:

```
Risiko tinggi
```

Namun jika:

```
nilai_user = 295
```

maka:

```
nilai_user > PG
```

status:

```
Zona aman
```

Proses ini menggunakan:

**Rule-Based Decision System**

---

# 9. Mengapa Menggunakan Beberapa Skenario?

Karena masa depan tidak memiliki satu kondisi tunggal.

Model deterministik:

```
Jika A maka B
```

kurang cocok.

Model skenario:

```
Jika kondisi rendah:
hasil X

Jika kondisi normal:
hasil Y

Jika kondisi tinggi:
hasil Z
```

lebih sesuai untuk masalah yang dipengaruhi perilaku manusia.

Pendekatan ini disebut:

**Scenario Planning Simulation**

---

# 10. Keterbatasan Model

Model ini tidak bertujuan menggantikan hasil resmi seleksi.

Batasan:

1. Preferensi siswa asli dapat berbeda.
2. Distribusi kandidat sintetis adalah asumsi.
3. Perubahan kebijakan dapat mengubah hasil.
4. Perilaku manusia tidak selalu mengikuti pola statistik.

Karena itu output harus dibaca sebagai:

> estimasi risiko dan strategi pilihan, bukan kepastian penerimaan.

---

# 11. Kesimpulan

Simulator prediksi passing grade ini dapat dikategorikan sebagai:

**Scenario-Based Predictive Admission Simulation Engine**

yang menggabungkan:

* Finite State Machine
* Parameterized Simulation
* Synthetic Population Modeling
* Weighted Preference Distribution
* Constraint-Based Allocation
* Top-K Ranking Algorithm
* Rule-Based Risk Analysis

Tujuan utama sistem bukan sekadar menghitung nilai, tetapi memodelkan **dinamika kompetisi** ketika banyak kandidat bersaing terhadap jumlah kursi yang terbatas.
 
