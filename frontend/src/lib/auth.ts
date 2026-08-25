import { NextRequest } from 'next/server';
import jwt from 'jsonwebtoken';
import bcrypt from 'bcryptjs';
import { prisma } from './prisma';

const JWT_SECRET = process.env.JWT_SECRET || 'umoja_sacco_super_secure_jwt_secret_key_2026_bezalel_tech';

export interface AuthSession {
  userId: number;
  userType: 'admin' | 'member';
  roleId?: number;
  role?: string;
  email?: string;
}

export function signToken(payload: AuthSession, expiresIn: string = '7d'): string {
  return jwt.sign(payload, JWT_SECRET, { expiresIn });
}

export function verifyToken(token: string): AuthSession | null {
  try {
    return jwt.verify(token, JWT_SECRET) as AuthSession;
  } catch {
    return null;
  }
}

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 10);
}

export async function verifyPassword(plain: string, hash: string): Promise<boolean> {
  if (!hash) return false;
  // Handle PHP password_hash or md5 fallback if any
  try {
    return await bcrypt.compare(plain, hash);
  } catch {
    return false;
  }
}

export async function getAuthSession(request: NextRequest): Promise<AuthSession | null> {
  // Check Authorization Bearer header
  const authHeader = request.headers.get('authorization');
  if (authHeader && authHeader.startsWith('Bearer ')) {
    const token = authHeader.substring(7);
    const session = verifyToken(token);
    if (session) return session;
  }

  // Check usms_token or session cookie
  const cookieToken = request.cookies.get('usms_token')?.value;
  if (cookieToken) {
    const session = verifyToken(cookieToken);
    if (session) return session;
  }

  return null;
}
