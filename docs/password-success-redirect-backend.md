# Mobile-Origin Password Success Redirect — Backend Brief

## Goal

Tambahkan behavior di backend/web agar **setelah password berhasil diubah**, user yang berasal dari **flow mobile** diarahkan kembali ke aplikasi mobile.

## Requirement

1. Flow password yang berasal dari **mobile-origin**:

   * setelah submit sukses di halaman web
   * diarahkan ke aplikasi mobile melalui deep link / app link

2. Flow password yang berasal dari **web-origin**:

   * tetap mengikuti flow web biasa
   * jangan diubah perilakunya jika tidak perlu

3. Jangan merusak flow password unified yang sudah berhasil dibuat.

4. Harus ada fallback aman:

   * jika app mobile terpasang → buka app
   * jika app mobile tidak terpasang → tetap ke fallback web yang aman/sukses

## Scope

Backend/web only.

Fokus pada:

* route
* controller
* request context/source detection
* redirect success handling
* email link context jika diperlukan
* fallback redirect strategy

## What to Audit

1. bagaimana flow unified password saat ini berjalan
2. bagaimana membedakan request yang berasal dari mobile vs web
3. di titik mana source/origin terbaik disimpan:

   * query param
   * signed param
   * session
   * DB request record
4. bagaimana success redirect saat ini bekerja
5. bagaimana menambahkan redirect ke app hanya untuk mobile-origin

## Implementation Rule

* jangan ubah FE web lebih dari yang benar-benar diperlukan
* jangan buat flow password baru
* backend harus memakai flow password unified yang sudah ada
* hanya tambahkan kemampuan redirect sukses khusus mobile-origin

## Expected Outcome

* mobile-origin success → redirect ke app
* web-origin success → tetap web flow
* fallback aman jika app tidak terpasang
* unified flow tetap utuh

## Output Expected from Codex

1. Audit findings
2. Strategi membedakan mobile-origin vs web-origin
3. Patch minimal
4. Implementasi
5. Verification checklist
