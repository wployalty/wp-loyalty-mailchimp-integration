import React from 'react';
import Icon from "./Icon";

const DropdownWrapper = ({options, value, handleDropDownClick, label, width = "w-full"}) => {
    const [show, setShow] = React.useState(false);
    return <div
        onClick={() => setShow(!show)}
        className={`border border-card_border relative rounded-md flex items-center h-11 justify-between 2xl:p-2 p-1.5 ${width} cursor-pointer`}>
        <p className={`text-dark text-xs 2xl:text-sm font-medium tracking-wide`}>{label}</p>
        <Icon icon={"arrow-down"} color={"text-dark"}
        />
        {show && <div
            className={`flex   flex-col border rounded-lg bg-white w-full text-light border-light_border z-10 absolute top-11.5 left-0 overflow-hidden`}>
            {
                options.map((item, index) => {
                    return <p
                        key={index}
                        onClick={() => handleDropDownClick(item)}
                        className={`flex items-center  px-4 py-2 justify-between 
                                            ${item.value === value ? "bg-primary_extra_light text-primary" : "bg-white text-dark "} 
                                            hover:bg-primary_extra_light cursor-pointer hover:bg-opacity-50`}
                    >
                        {item.label}
                        {item.value === value &&
                            <span className='flex items-center'>
                                                <i
                                                    className=" wlr wlrf-tick color-important font-medium  text-lg 2xl:text-xl leading-0 cursor-pointer "
                                                />
                                                 </span>
                        }
                    </p>
                })
            }
        </div>

        }
    </div>
};

export default DropdownWrapper;