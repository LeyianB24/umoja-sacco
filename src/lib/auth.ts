import { NextRequest } from 'next/server';
import jwt, { SignOptions } from 'jsonwebtoken';
import bcrypt from 'bcryptjs';
import crypto from 'crypto';

const JWT_SECRET = process.env.JWT_SECRET || (
  process.env.NODE_ENV === 'production'
    ? (() => { throw new Error('CRITICAL: JWT_SECRET environment variable is missing in production.'); })()
    : 'umoja_sacco_super_secure_jwt_secret_key_2026_bezalel_tech'
);

export interface AuthSession {
  userId: number;
  userType: 'admin' | 'member';
  roleId?: number;
  role?: string;
  email?: string;
}

export function signToken(payload: AuthSession, expiresIn: SignOptions['expiresIn'] = '7d'): string {
  const options: SignOptions = { expiresIn };
  return jwt.sign(payload, JWT_SECRET, options);
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
  if (!hash || !plain) return false;

  // 1. Check legacy PHP SHA-256 hash (64 hex characters)
  if (hash.length === 64) {
    const sha256 = crypto.createHash('sha256').update(plain).digest('hex');
    if (sha256.toLowerCase() === hash.toLowerCase()) {
      return true;
    }
  }

  // 2. Check legacy MD5 hash (32 hex characters)
  if (hash.length === 32) {
    const md5 = crypto.createHash('md5').update(plain).digest('hex');
    if (md5.toLowerCase() === hash.toLowerCase()) {
      return true;
    }
  }

  // 3. Handle standard bcrypt & PHP password_hash ($2y$, $2b$, $2a$)
  try {
    const normalizedHash = hash.startsWith('$2y$') ? '$2a$' + hash.substring(4) : hash;
    return await bcrypt.compare(plain, normalizedHash);
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
