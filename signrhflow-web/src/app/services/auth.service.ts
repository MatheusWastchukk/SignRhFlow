import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly tokenKey = 'signrhflow_auth_token';
  private readonly authState$ = new BehaviorSubject<boolean>(this.hasToken());

  isAuthenticated$ = this.authState$.asObservable();

  isAuthenticated(): boolean {
    return this.authState$.value;
  }

  getToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  setToken(token: string): void {
    localStorage.setItem(this.tokenKey, token);
    this.authState$.next(true);
  }

  clearToken(): void {
    localStorage.removeItem(this.tokenKey);
    this.authState$.next(false);
  }

  private hasToken(): boolean {
    const token = localStorage.getItem(this.tokenKey);
    return typeof token === 'string' && token.length > 0;
  }
}
