<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    // الحقول التي يسمح لارافل بتعبئتها مباشرة (Mass Assignment)
    // اخترناها بناءً على الأعمدة في صورة الـ ERD
    protected $fillable = [
        'name',     // اسم الموظف
        'email',    // البريد الإلكتروني (للتسجيل)
        'password', // كلمة المرور
        'phone',    // رقم الهاتف
        'role',     // الدور: admin أو pharmacist
        'is_active' // حالة الحساب: نشط أم معطل
    ];

    // حماية كلمة المرور عند عرض البيانات
    protected $hidden = [
        'password',
        'remember_token',
    ];
}
