# Development Guidelines

## Domain-Centric Packaging

Use **Domain-Centric packaging** to organize code by business domain rather than technical layers. This approach improves maintainability, scalability, and team ownership.

### Structure
```
com.estee.ccr.cms.{domain}/
├── domain/        # Data layer (entities, repositories)
├── model/         # Domain models/DTOs
├── mapper/        # Data transformation (MapStruct)
├── cache/         # Caching layer (cache implementations)
└── service/       # Business logic layer
```

### Example
```
com.estee.ccr.cms.profile/
├── domain/
│   ├── ProfileEntity.java
│   ├── ProfileRepository.java
│   └── BaseAuditEntity.java
├── model/
│   ├── Profile.java
│   ├── ProfileStatus.java
│   └── ProfileVersion.java
├── mapper/
│   ├── ProfileMapper.java
│   └── ProfileVersionMapper.java
├── cache/
│   └── ProfileCache.java
└── service/
    └── ProfileService.java
```

### Benefits
- **Domain Focus**: Each package represents a business domain
- **Encapsulation**: Domain logic is contained within its package
- **Scalability**: Easy to add new domains without affecting existing ones
- **Team Ownership**: Different teams can own different domains
- **Testability**: Domain-specific tests are co-located

## Logging Guidelines

Developers should make a conscious effort to distinguish between `DEBUG` and `INFO` log levels:

### DEBUG Logging
- **Purpose**: Detailed debugging information for development
- **Assumption**: Will be disabled in remote/production environments
- **Use Cases**:
  - Logging objects and low-level implementation details
  - Method entry/exit with parameters
  - Internal state changes
  - Detailed flow tracing

### INFO Logging
- **Purpose**: Important business events and support activities
- **Assumption**: Will be enabled in remote/production environments
- **Use Cases**:
  - Full business transactions (start/complete)
- **Key information for support activities**:
  - User actions and business operations
  - External system interactions
  - Performance metrics and timing
  - Error conditions and recovery actions
  - Configuration changes and system events

### Best Practices
- **Avoid superfluous logging** - Don't log obvious operations
- **Use structured logging** - Include relevant context and IDs
- **Log at appropriate levels** - DEBUG for development, INFO for production support
- **Include key identifiers** - User IDs, transaction IDs, request IDs for traceability

## Dependency Injection Guidelines

**Always use constructor injection** instead of field-level injection (`@Autowired` on fields).

### Why Constructor Injection?
- **Immutability**: Dependencies are final and cannot be changed after construction
- **Testability**: Easy to provide mock dependencies in unit tests
- **Null Safety**: Dependencies are guaranteed to be non-null
- **Clear Dependencies**: Constructor signature clearly shows all required dependencies
- **Spring Best Practice**: Recommended by Spring Framework documentation

### Example
```java
// ✅ Good - Constructor injection
@Service
@RequiredArgsConstructor
public class ProfileService {
    private final ProfileRepository profileRepository;
    private final ProfileMapper profileMapper;
    
    // Lombok @RequiredArgsConstructor generates constructor automatically
}

// ❌ Avoid - Field injection
@Service
public class ProfileService {
    @Autowired
    private ProfileRepository profileRepository;
    
    @Autowired
    private ProfileMapper profileMapper;
}
```

### Test Examples
```java
// ✅ Good - Constructor injection in unit tests
class ProfileServiceTest {
    private final ProfileRepository mockRepository = mock(ProfileRepository.class);
    private final ProfileMapper mockMapper = mock(ProfileMapper.class);
    private final ProfileService profileService = new ProfileService(mockRepository, mockMapper);
}

// ✅ Good - Field injection in Spring Boot integration tests
@SpringBootTest
class ProfileServiceIntegrationTest {
    @Autowired
    private ProfileRepository profileRepository;
    
    @Autowired
    private ProfileService profileService;
}
```

### Integration Test Exception
For **Spring Boot integration tests** (`@SpringBootTest`, `@DataJpaTest`, etc.), field injection with `@Autowired` is the standard and recommended approach because:
- JUnit 5 doesn't automatically resolve Spring bean constructor parameters
- Spring Boot test framework handles field injection seamlessly
- It's the pattern used in Spring Boot documentation and examples

## Null Safety Guidelines

**Prefer using `Optional<T>` instead of returning `null`** for methods that might not have a result.

### Why Use Optional?
- **Explicit Intent**: Makes it clear that a method might not return a value
- **Null Safety**: Prevents `NullPointerException` at runtime
- **API Clarity**: Forces callers to handle the "no result" case explicitly
- **Functional Programming**: Enables safe chaining and transformation operations
- **IDE Support**: Better autocomplete and static analysis

### Example
```java
// ✅ Good - Using Optional
@Service
@RequiredArgsConstructor
public class ProfileService {
    private final ProfileRepository profileRepository;
    
    public Optional<Profile> getProfileById(Integer profileId) {
        if (profileId == null) {
            return Optional.empty();
        }
        return profileRepository.findById(profileId)
                .map(profileMapper::toProfile);
    }
}

// ❌ Avoid - Returning null
@Service
public class ProfileService {
    public Profile getProfileById(Integer profileId) {
        if (profileId == null) {
            return null; // Dangerous - caller must remember to check
        }
        ProfileEntity entity = profileRepository.findById(profileId).orElse(null);
        return entity != null ? profileMapper.toProfile(entity) : null;
    }
}
```

### Controller Usage
```java
// ✅ Good - Controller handling Optional
@GetMapping("/{profileId}")
public ResponseEntity<Profile> getProfileById(@PathVariable Integer profileId) {
    return profileService.getProfileById(profileId)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
}

// ❌ Avoid - Manual null checking
@GetMapping("/{profileId}")
public ResponseEntity<Profile> getProfileById(@PathVariable Integer profileId) {
    Profile profile = profileService.getProfileById(profileId);
    if (profile != null) {
        return ResponseEntity.ok(profile);
    } else {
        return ResponseEntity.notFound().build();
    }
}
```

### Best Practices
- **Service Layer**: Always return `Optional<T>` for methods that might not find a result
- **Repository Layer**: Spring Data JPA already returns `Optional<T>` for `findById()`
- **Controller Layer**: Use `Optional.map()` and `orElse()` for clean response handling
- **Never return `null`**: Use `Optional.empty()` instead
- **Handle null inputs**: Check for null parameters and return `Optional.empty()`

## Exception Handling Pattern

**Use custom exceptions with global exception handlers** for consistent error responses and proper HTTP status codes.

### Why This Pattern?
- **Consistent**: All validation errors return appropriate HTTP status codes
- **Centralized**: One place to handle specific exception types
- **Logged**: Proper logging with structured format
- **Extensible**: Easy to add more validation exception types
- **Clear**: Separates business logic from error handling

### 1. Custom Exception with Response Status
```java
@ResponseStatus(HttpStatus.BAD_REQUEST)
public class FilterValidationException extends RuntimeException {
    public FilterValidationException(String message) {
        super(message);
    }
}
```

### 2. Global Exception Handler
```java
@Slf4j
@RestControllerAdvice
public class FilterExceptionHandler {

    @ExceptionHandler(FilterValidationException.class)
    public ResponseEntity<String> handleFilterValidation(FilterValidationException ex) {
        log.warn("Filter validation error! message:[{}]", ex.getMessage());
        return ResponseEntity.badRequest().body(ex.getMessage());
    }
}
```

### 3. Throw in Service Layer
```java
public Predicate<Profile> buildPredicate(FilterField filter) {
    if (!isValidComparator(filter.getComparator())) {
        throw new FilterValidationException("Invalid comparator: " + filter.getComparator());
    }
    // ... rest of logic
}
```

### For New Validation Types
1. **Create specific exception** extending `RuntimeException` with `@ResponseStatus`
2. **Add handler method** to existing `@RestControllerAdvice` class
3. **Throw exception** in service layer when validation fails

### Benefits
- **Proper HTTP Status**: Returns 400 Bad Request instead of misleading 403 Forbidden
- **Structured Logging**: Consistent log format for debugging
- **Reusable Pattern**: Other developers can follow the same approach
- **Spring Security Compatible**: Doesn't interfere with authentication/authorization
