import { NextResponse } from 'next/server';
import { apiSuccess } from '@/lib/api-response';

export async function POST() {
  const response = apiSuccess(null, 'Logged out successfully.');
  response.cookies.set('usms_token', '', {
    httpOnly: true,
    expires: new Date(0),
    path: '/',
  });
  return response;
}

export async function GET() {
  return POST();
}
