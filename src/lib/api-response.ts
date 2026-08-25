import { NextResponse } from 'next/server';

export function apiSuccess<T = any>(data: T, message: string = 'Success', status: number = 200) {
  return NextResponse.json(
    {
      status: 'success',
      message,
      data,
    },
    { status }
  );
}

export function apiError(message: string = 'Internal server error', status: number = 400, errors?: any) {
  return NextResponse.json(
    {
      status: 'error',
      message,
      ...(errors ? { errors } : {}),
    },
    { status }
  );
}
