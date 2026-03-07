import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Contract, Employee, PaginatedResponse } from '../models';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly baseUrl = 'http://localhost:8000/api';

  constructor(private readonly http: HttpClient) {}

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
}
