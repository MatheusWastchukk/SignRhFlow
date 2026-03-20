import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Contract, Employee, PaginatedResponse, SigningContextResponse } from '../models';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly baseUrl = 'http://localhost:8000/api';

  constructor(private readonly http: HttpClient) {}

  login(payload: { login: string; password: string }): Observable<{
    token: string;
    token_type: string;
    expires_at: string;
    user: { id: string; name: string; email: string };
  }> {
    return this.http.post<{
      token: string;
      token_type: string;
      expires_at: string;
      user: { id: string; name: string; email: string };
    }>(`${this.baseUrl}/auth/login`, payload);
  }

  logout(): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.baseUrl}/auth/logout`, {});
  }

  me(): Observable<{ id: string; name: string; email: string }> {
    return this.http.get<{ id: string; name: string; email: string }>(`${this.baseUrl}/auth/me`);
  }

  listContracts(): Observable<PaginatedResponse<Contract>> {
    return this.http.get<PaginatedResponse<Contract>>(`${this.baseUrl}/contracts`);
  }

  createEmployee(payload: Pick<Employee, 'name' | 'email' | 'phone' | 'cpf'>): Observable<Employee> {
    return this.http.post<Employee>(`${this.baseUrl}/employees`, payload);
  }

  createContract(payload: {
    employee_id: string;
    delivery_method: 'EMAIL' | 'WHATSAPP';
  }): Observable<Contract> {
    return this.http.post<Contract>(`${this.baseUrl}/contracts`, payload);
  }

  deleteContract(contractId: string): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.baseUrl}/contracts/${contractId}`);
  }

  getSigningContext(token: string): Observable<SigningContextResponse> {
    return this.http.get<SigningContextResponse>(`${this.baseUrl}/signing/${token}/context`);
  }

  saveSignerData(token: string, payload: {
    name: string;
    email: string;
    cpf: string;
    phone: string;
    phone_country: 'BR' | 'US' | 'PT';
  }): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.baseUrl}/signing/${token}/signer-data`, payload);
  }

  finalizeSigning(token: string, payload: {
    signed_name?: string;
    delivery_method: 'EMAIL' | 'WHATSAPP';
  }): Observable<{
    message: string;
    signed_at: string | null;
    delivery_method: 'EMAIL' | 'WHATSAPP';
    status: 'DRAFT' | 'PENDING' | 'SIGNED' | 'REJECTED';
  }> {
    return this.http.post<{
      message: string;
      signed_at: string | null;
      delivery_method: 'EMAIL' | 'WHATSAPP';
      status: 'DRAFT' | 'PENDING' | 'SIGNED' | 'REJECTED';
    }>(`${this.baseUrl}/signing/${token}/finalize`, payload);
  }
}
