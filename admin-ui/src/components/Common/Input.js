import React from 'react';

const Input = ({
                   placeHolder = null,
                   type = "text",
                   value,
                   required,
                   onChange,
                   textColor = "text-dark",
                   border,
                   others = "",
                   onfocus = null,
                   onblur = null,
                   min = null,
                   max = null,
                   error = false,
                   id,
                   padding = "2xl:p-2.5 p-1.5",
                   onKeyDown,
                   height = `${["text", "number"].includes(type) ? "h-11" : "h-20"}`
               }) => {
    return type === "textarea" ? (
            <textarea
                id={id}
                value={value}
                required={required}
                placeholder={placeHolder}
                className={`${padding}  transition duration-200 ease-in focus:outline-none rounded focus:shadow-none antialiased bg-white ${border} 2xl:focus:border-2  w-full  ${textColor} ${others} ${error && "wll_input-error"}`}
                onChange={onChange}
                onFocus={onfocus}
            />
        ) :
        (
            <input
                id={id}
                type={type}
                value={value}
                required={required}
                min={type == "number" ? 0 && !min : min}
                max={max}
                placeholder={placeHolder}
                className={`${padding} ${height} transition duration-200 ease-in focus:outline-none rounded focus:shadow-none antialiased bg-white ${border} 
      2xl:focus:border-2 w-full ${textColor}  tracking-wider ${others} ${error && "wll_input-error"} `}
                onChange={onChange}
                onFocus={onfocus}
                onBlur={onblur}
                onKeyDown={onKeyDown}
            />
        );
};

export default Input;