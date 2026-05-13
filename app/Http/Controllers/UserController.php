<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // 1. عرض كل الموظفين في الصيدلية
    public function index()
    {
        $users = User::all(); // جلب كل المستخدمين من قاعدة البيانات
        return response()->json($users); // return view('users.index', compact('users')); // إرسالهم لصفحة العرض
    }

    // 2. تخزين موظف جديد (بعد تعبئة الفورم)
    public function store(Request $request)
    {
        // التأكد من صحة البيانات المدخلة
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required' // admin أو pharmacist
        ]);

        // حفظ البيانات في الجدول
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // تشفير كلمة المرور (ضروري للأمان)
            'phone' => $request->phone,
            'role' => $request->role,
            'is_active' => true // افتراضياً يكون الحساب نشطاً
        ]);

        return response()->json(['message' => 'Saved successfully', 'user' => $user]);
    }

    // 3. حذف موظف
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    // تسجيل دخول
    public function login(Request $request)
    {
        // 1. التأكد أن المستخدم أرسل البريد وكلمة المرور
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // 2. محاولة تسجيل الدخول بالبيانات المرسلة
        // Auth::attempt تقوم بتشفير كلمة المرور المدخلة ومقارنتها بالموجودة في القاعدة تلقائياً
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user(); // جلب بيانات المستخدم الذي نجح في الدخول

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged in !',
                'user' => $user
            ], 200);
        }

        // 3. إذا كانت البيانات خاطئة
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid email or password.'
        ], 401);
    }
}
